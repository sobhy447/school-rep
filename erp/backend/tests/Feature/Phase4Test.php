<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\PettyCashItem;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private CostCenter $cc;
    private FiscalYear $fy;
    private PettyCashItem $item;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function env(): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $modules = ['accounts', 'journals', 'vouchers', 'petty_cash', 'settlements', 'settings', 'customers'];
        $acts = ['view', 'create', 'edit', 'delete', 'approve', 'post', 'unpost', 'lock'];
        $keys = [];
        foreach ($modules as $m) foreach ($acts as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k],
            ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م',
            'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);

        $mk = fn ($code, $name, $type, $opts = []) => Account::create(array_merge(
            ['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $opts));
        $this->acc['cash'] = $mk('1101', 'الصندوق', 'ASSET', ['is_cash_or_bank' => true]);
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);
        $this->acc['custody'] = $mk('1104', 'عهد', 'ASSET');
        $this->acc['expense'] = $mk('51', 'مصروفات', 'EXPENSE');
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['income_summary'] = $mk('39', 'النتيجة', 'EQUITY');
        $this->acc['retained'] = $mk('32', 'أرباح محتجزة', 'EQUITY');

        $this->cc = CostCenter::create(['company_id' => $cid, 'code' => 'CC1', 'name' => 'إدارة']);
        $this->item = PettyCashItem::create(['company_id' => $cid, 'code' => 'PRINT', 'name' => 'طباعة',
            'default_amount' => 5, 'max_repeat' => 1, 'expense_account_id' => $this->acc['expense']->id]);

        CompanySetting::put($cid, 'petty_cash_account_id', (string) $this->acc['custody']->id);
        CompanySetting::put($cid, 'income_summary_account_id', (string) $this->acc['income_summary']->id);
        CompanySetting::put($cid, 'retained_earnings_account_id', (string) $this->acc['retained']->id);

        return $user;
    }

    /** ينشئ قيداً مُرحَّلاً مباشرة (fixture). */
    private function postedEntry(string $type, array $lines): JournalEntry
    {
        $cid = $this->company->id;
        $td = round(array_sum(array_column($lines, 'debit')), 3);
        $tc = round(array_sum(array_column($lines, 'credit')), 3);
        $entry = JournalEntry::create([
            'company_id' => $cid, 'fiscal_year_id' => $this->fy->id, 'type' => $type,
            'entry_number' => $type . '-' . uniqid(), 'entry_date' => '2026-03-01',
            'status' => 'POSTED', 'total_debit' => $td, 'total_credit' => $tc,
        ]);
        foreach ($lines as $i => $l) {
            $entry->lines()->create([
                'company_id' => $cid, 'line_number' => $i + 1,
                'account_id' => $l['account_id'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0,
                'description' => $l['description'] ?? null,
            ]);
        }
        return $entry->load('lines');
    }

    // ───────── العهد ─────────

    public function test_create_expense_claim(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/expense-claims', [
            'claim_date' => '2026-02-01', 'description' => 'مصاريف',
            'lines' => [['petty_cash_item_id' => $this->item->id, 'amount' => 5, 'cost_center_id' => $this->cc->id]],
        ])->assertStatus(201)->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.total_amount', '5.000');
    }

    public function test_repeat_limit_hard_block(): void
    {
        $user = $this->env();
        $payload = fn () => [
            'claim_date' => '2026-02-01',
            'lines' => [['petty_cash_item_id' => $this->item->id, 'amount' => 5, 'cost_center_id' => $this->cc->id]],
        ];
        // الأولى تنجح (max_repeat = 1)
        $this->actingAs($user)->postJson('/api/expense-claims', $payload())->assertStatus(201);
        // الثانية تُرفض (تجاوز الحد)
        $this->actingAs($user)->postJson('/api/expense-claims', $payload())->assertStatus(422);
    }

    public function test_convert_claim_creates_posted_entry_crediting_custody(): void
    {
        $user = $this->env();
        // بند بلا حد تكرار لتجنّب الحظر
        $item2 = PettyCashItem::create(['company_id' => $this->company->id, 'code' => 'TR', 'name' => 'مواصلات',
            'expense_account_id' => $this->acc['expense']->id]);

        $claimId = $this->actingAs($user)->postJson('/api/expense-claims', [
            'claim_date' => '2026-02-10',
            'lines' => [['petty_cash_item_id' => $item2->id, 'amount' => 30, 'cost_center_id' => $this->cc->id]],
        ])->json('data.id');

        $this->actingAs($user)->postJson("/api/expense-claims/{$claimId}/approve")->assertOk();
        $res = $this->actingAs($user)->postJson("/api/expense-claims/{$claimId}/convert", ['fiscal_year_id' => $this->fy->id])
            ->assertOk();
        $res->assertJsonPath('data.status', 'CONVERTED');

        $entryId = $res->json('data.journal_entry_id');
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($entryId);
        $this->assertSame('POSTED', $entry->status);
        $custodyLine = $entry->lines->firstWhere('account_id', $this->acc['custody']->id);
        $this->assertSame('30.000', $custodyLine->credit); // العهدة دائنة بالإجمالي
    }

    // ───────── الأمانات/السداد ─────────

    public function test_settlement_view_and_partial_then_full_allocation(): void
    {
        $user = $this->env();
        $custId = $this->acc['customer']->id;
        // أمانة: العميل دائن 500 (سند قبض)
        $trust = $this->postedEntry('RECEIPT', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 500],
            ['account_id' => $custId, 'credit' => 500, 'description' => 'أمانة'],
        ]);
        // استحقاق: العميل مدين 300
        $ent = $this->postedEntry('MANUAL', [
            ['account_id' => $custId, 'debit' => 300, 'description' => 'أتعاب'],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 300],
        ]);
        $trustLine = $trust->lines->firstWhere('credit', '500.000');
        $entLine = $ent->lines->firstWhere('debit', '300.000');

        // العرض: أمانة حمراء + استحقاق
        $view = $this->actingAs($user)->getJson("/api/settlements/customer/{$custId}")->assertOk();
        $view->assertJsonPath('data.trusts.0.color', 'RED')
             ->assertJsonPath('data.trusts.0.remaining', 500)
             ->assertJsonPath('data.entitlements.0.remaining', 300);

        // سداد جزئي 200 ➜ الأمانة تصير صفراء (باقي 300)، الاستحقاق باقي 100
        $partial = $this->actingAs($user)->postJson("/api/settlements/customer/{$custId}/allocate", [
            'allocations' => [['trust_line_id' => $trustLine->id, 'entitlement_line_id' => $entLine->id, 'amount' => 200]],
        ])->assertOk();
        $partial->assertJsonPath('data.trusts.0.color', 'YELLOW')
                ->assertJsonPath('data.trusts.0.remaining', 300)
                ->assertJsonPath('data.entitlements.0.remaining', 100);

        // سداد باقي الاستحقاق 100 ➜ الاستحقاق يختفي، الأمانة باقي 200
        $full = $this->actingAs($user)->postJson("/api/settlements/customer/{$custId}/allocate", [
            'allocations' => [['trust_line_id' => $trustLine->id, 'entitlement_line_id' => $entLine->id, 'amount' => 100]],
        ])->assertOk();
        $full->assertJsonCount(0, 'data.entitlements')          // اختفى الاستحقاق (سُدّد بالكامل)
             ->assertJsonPath('data.trusts.0.remaining', 200);  // باقي أمانة
    }

    public function test_settlement_over_allocation_rejected(): void
    {
        $user = $this->env();
        $custId = $this->acc['customer']->id;
        $trust = $this->postedEntry('RECEIPT', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 100],
            ['account_id' => $custId, 'credit' => 100],
        ]);
        $ent = $this->postedEntry('MANUAL', [
            ['account_id' => $custId, 'debit' => 300],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 300],
        ]);
        $trustLine = $trust->lines->firstWhere('credit', '100.000');
        $entLine = $ent->lines->firstWhere('debit', '300.000');

        // محاولة سداد 150 من أمانة 100 ➜ تتجاوز باقي الأمانة
        $this->actingAs($user)->postJson("/api/settlements/customer/{$custId}/allocate", [
            'allocations' => [['trust_line_id' => $trustLine->id, 'entitlement_line_id' => $entLine->id, 'amount' => 150]],
        ])->assertStatus(422);
    }

    // ───────── الإقفال السنوي ─────────

    public function test_year_end_closing_computes_net_and_locks(): void
    {
        $user = $this->env();
        // إيراد 1000 (دائن) + مصروف 400 (مدين)
        $this->postedEntry('MANUAL', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 1000],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 1000],
        ]);
        $this->postedEntry('MANUAL', [
            ['account_id' => $this->acc['expense']->id, 'debit' => 400],
            ['account_id' => $this->acc['cash']->id, 'credit' => 400],
        ]);

        $res = $this->actingAs($user)->postJson('/api/closing/close-year', ['fiscal_year_id' => $this->fy->id])
            ->assertOk();
        $res->assertJsonPath('data.net_result', 600)
            ->assertJsonPath('data.result', 'ربح')
            ->assertJsonPath('data.fiscal_year.is_locked', true);

        // بعد الإقفال لا يمكن إدخال قيد في السنة المقفلة
        $this->actingAs($user)->postJson('/api/closing/close-year', ['fiscal_year_id' => $this->fy->id])
            ->assertStatus(422);
    }

    // ───────── كشف الحساب ─────────

    public function test_customer_statement_running_balance(): void
    {
        $user = $this->env();
        $custId = $this->acc['customer']->id;
        $this->postedEntry('MANUAL', [
            ['account_id' => $custId, 'debit' => 300, 'description' => 'أتعاب'],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 300],
        ]);
        $this->postedEntry('RECEIPT', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 100],
            ['account_id' => $custId, 'credit' => 100, 'description' => 'دفعة'],
        ]);

        $res = $this->actingAs($user)->getJson("/api/accounts/{$custId}/statement")->assertOk();
        // العميل أصل (مدين الطبيعة): 300 مدين ثم 100 دائن ➜ الرصيد 200
        $res->assertJsonPath('data.closing_balance', 200)
            ->assertJsonCount(2, 'data.lines');
    }

    public function test_parties_listing(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->getJson('/api/accounts/parties?type=CUSTOMER')->assertOk();
        $codes = collect($res->json('data'))->pluck('code')->all();
        $this->assertContains('1103', $codes);
        $this->assertNotContains('1101', $codes);
    }
}
