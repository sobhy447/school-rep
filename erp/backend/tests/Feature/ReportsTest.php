<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SettlementAllocation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private FiscalYear $fy;

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
        $keys = ['reports.view', 'accounts.view'];
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k],
            ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م',
            'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);

        $mk = fn ($code, $name, $type, $opts = []) => Account::create(array_merge(
            ['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $opts));
        $this->acc['cash'] = $mk('1101', 'الصندوق', 'ASSET', [
            'is_cash_or_bank' => true, 'opening_balance' => 1000, 'opening_balance_type' => 'DEBIT']);
        $this->acc['capital'] = $mk('31', 'رأس المال', 'EQUITY', [
            'opening_balance' => 1000, 'opening_balance_type' => 'CREDIT']);
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['expense'] = $mk('51', 'مصروفات', 'EXPENSE');
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);

        return $user;
    }

    private function postedEntry(string $type, array $lines, string $status = 'POSTED'): JournalEntry
    {
        $cid = $this->company->id;
        $entry = JournalEntry::create([
            'company_id' => $cid, 'fiscal_year_id' => $this->fy->id, 'type' => $type,
            'entry_number' => $type . '-' . uniqid(), 'entry_date' => '2026-03-01', 'status' => $status,
            'total_debit' => round(array_sum(array_column($lines, 'debit')), 3),
            'total_credit' => round(array_sum(array_column($lines, 'credit')), 3),
        ]);
        foreach ($lines as $i => $l) {
            $entry->lines()->create([
                'company_id' => $cid, 'line_number' => $i + 1, 'account_id' => $l['account_id'],
                'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'description' => $l['description'] ?? null,
            ]);
        }
        return $entry->load('lines');
    }

    private function seedMovements(): void
    {
        $this->postedEntry('MANUAL', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 500],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 500],
        ]);
        $this->postedEntry('MANUAL', [
            ['account_id' => $this->acc['expense']->id, 'debit' => 200],
            ['account_id' => $this->acc['cash']->id, 'credit' => 200],
        ]);
        $this->postedEntry('MANUAL', [
            ['account_id' => $this->acc['customer']->id, 'debit' => 300, 'description' => 'أتعاب'],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 300],
        ]);
        $this->postedEntry('RECEIPT', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 100],
            ['account_id' => $this->acc['customer']->id, 'credit' => 100],
        ]);
    }

    public function test_trial_balance_is_balanced(): void
    {
        $user = $this->env();
        $this->seedMovements();

        $res = $this->actingAs($user)->getJson('/api/reports/trial-balance')->assertOk();
        $res->assertJsonPath('data.balanced', true)
            ->assertJsonPath('data.total_debit', 1800)
            ->assertJsonPath('data.total_credit', 1800);
    }

    public function test_income_statement(): void
    {
        $user = $this->env();
        $this->seedMovements();

        $this->actingAs($user)->getJson('/api/reports/income-statement')->assertOk()
            ->assertJsonPath('data.total_revenues', 800)
            ->assertJsonPath('data.total_expenses', 200)
            ->assertJsonPath('data.net_profit', 600);
    }

    public function test_balance_sheet_balanced(): void
    {
        $user = $this->env();
        $this->seedMovements();

        $this->actingAs($user)->getJson('/api/reports/balance-sheet')->assertOk()
            ->assertJsonPath('data.total_assets', 1600)
            ->assertJsonPath('data.net_profit', 600)
            ->assertJsonPath('data.total_liabilities_and_equity', 1600)
            ->assertJsonPath('data.balanced', true);
    }

    public function test_cash_flow(): void
    {
        $user = $this->env();
        $this->seedMovements();

        $this->actingAs($user)->getJson('/api/reports/cash-flow')->assertOk()
            ->assertJsonPath('data.total_inflow', 600)
            ->assertJsonPath('data.total_outflow', 200)
            ->assertJsonPath('data.net_cash_flow', 400);
    }

    public function test_reports_ignore_draft_entries(): void
    {
        $user = $this->env();
        $this->seedMovements();
        // قيد مسوّدة بإيراد كبير — يجب ألا يظهر في التقارير
        $this->postedEntry('MANUAL', [
            ['account_id' => $this->acc['cash']->id, 'debit' => 999],
            ['account_id' => $this->acc['revenue']->id, 'credit' => 999],
        ], 'DRAFT');

        $this->actingAs($user)->getJson('/api/reports/income-statement')->assertOk()
            ->assertJsonPath('data.total_revenues', 800); // بدون الـ 999
    }

    public function test_claims_report_with_settlement(): void
    {
        $user = $this->env();
        $this->seedMovements();
        // أنشئ سطر استحقاق وسطر أمانة وخصّص 100
        $ent = JournalEntry::withoutGlobalScopes()->where('company_id', $this->company->id)
            ->where('type', 'MANUAL')->get()
            ->first(fn ($e) => $e->lines()->where('account_id', $this->acc['customer']->id)->where('debit', '>', 0)->exists());
        $entLine = $ent->lines()->where('account_id', $this->acc['customer']->id)->where('debit', '>', 0)->first();
        $trust = JournalEntry::withoutGlobalScopes()->where('company_id', $this->company->id)->where('type', 'RECEIPT')->first();
        $trustLine = $trust->lines()->where('account_id', $this->acc['customer']->id)->where('credit', '>', 0)->first();

        SettlementAllocation::create([
            'company_id' => $this->company->id, 'customer_account_id' => $this->acc['customer']->id,
            'trust_line_id' => $trustLine->id, 'entitlement_line_id' => $entLine->id,
            'amount' => 100, 'allocation_date' => '2026-03-02',
        ]);

        // تفصيلي
        $detail = $this->actingAs($user)->getJson('/api/reports/claims?mode=detailed')->assertOk();
        $detail->assertJsonPath('data.rows.0.amount', 300)
               ->assertJsonPath('data.rows.0.paid', 100)
               ->assertJsonPath('data.rows.0.remaining', 200);

        // مجمّع
        $grouped = $this->actingAs($user)->getJson('/api/reports/claims?mode=grouped')->assertOk();
        $grouped->assertJsonPath('data.rows.0.amount', 300)
                ->assertJsonPath('data.rows.0.remaining', 200);
    }

    public function test_dashboard(): void
    {
        $user = $this->env();
        $this->seedMovements();

        $this->actingAs($user)->getJson('/api/reports/dashboard')->assertOk()
            ->assertJsonPath('data.total_assets', 1600)
            ->assertJsonPath('data.net_profit', 600)
            ->assertJsonPath('data.net_cash_flow', 400)
            ->assertJsonPath('data.balance_sheet_balanced', true);
    }

    public function test_general_ledger_running_balance(): void
    {
        $user = $this->env();
        $this->seedMovements();

        $this->actingAs($user)->getJson("/api/reports/general-ledger/{$this->acc['cash']->id}")->assertOk()
            ->assertJsonPath('data.opening_balance', 1000)
            ->assertJsonPath('data.closing_balance', 1400); // 1000 +500 -200 +100
    }

    public function test_reports_require_permission(): void
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $this->company->id, 'slug' => 'r', 'name' => 'د']);
        $user = User::create(['company_id' => $this->company->id, 'role_id' => $role->id, 'name' => 'م',
            'email' => 'np' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $this->actingAs($user)->getJson('/api/reports/trial-balance')->assertStatus(403);
    }
}
