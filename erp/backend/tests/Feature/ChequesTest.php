<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChequesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private FiscalYear $fy;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function env(): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $keys = [];
        foreach (['cheques', 'accounts', 'reports'] as $m) foreach (['view', 'create', 'edit'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($c, $nm, $t, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $c, 'name' => $nm, 'type' => $t], $o));
        $this->acc['bank'] = $mk('1102', 'البنك', 'ASSET', ['is_cash_or_bank' => true]);
        $this->acc['collection'] = $mk('1107', 'شيكات تحت التحصيل', 'ASSET');
        $this->acc['payable'] = $mk('27', 'شيكات الدفع', 'LIABILITY');
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);
        $this->acc['vendor'] = $mk('21', 'الموردون', 'LIABILITY', ['party_type' => 'VENDOR']);
        CompanySetting::put($cid, 'cheques_collection_account_id', (string) $this->acc['collection']->id);
        CompanySetting::put($cid, 'cheques_payable_account_id', (string) $this->acc['payable']->id);
        return $user;
    }

    private function incoming(User $user): int
    {
        return $this->actingAs($user)->postJson('/api/cheques', [
            'type' => 'INCOMING', 'fiscal_year_id' => $this->fy->id, 'cheque_number' => 'CHK1', 'amount' => 400,
            'issue_date' => '2026-02-01', 'due_date' => '2026-03-01',
            'party_account_id' => $this->acc['customer']->id, 'bank_account_id' => $this->acc['bank']->id,
        ])->assertStatus(201)->json('data.id');
    }

    public function test_register_incoming_creates_entry(): void
    {
        $user = $this->env();
        $id = $this->incoming($user);
        $chq = Cheque::withoutGlobalScopes()->find($id);
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($chq->register_entry_id);
        // مدين شيكات تحت التحصيل / دائن العميل
        $this->assertSame('400.000', $entry->lines->firstWhere('account_id', $this->acc['collection']->id)->debit);
        $this->assertSame('400.000', $entry->lines->firstWhere('account_id', $this->acc['customer']->id)->credit);
    }

    public function test_clear_incoming_debits_bank(): void
    {
        $user = $this->env();
        $id = $this->incoming($user);
        $res = $this->actingAs($user)->postJson("/api/cheques/{$id}/clear", ['date' => '2026-03-02'])->assertOk();
        $res->assertJsonPath('data.status', 'CLEARED');
        $chq = Cheque::withoutGlobalScopes()->find($id);
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($chq->clear_entry_id);
        $this->assertSame('400.000', $entry->lines->firstWhere('account_id', $this->acc['bank']->id)->debit);
        $this->assertSame('400.000', $entry->lines->firstWhere('account_id', $this->acc['collection']->id)->credit);
    }

    public function test_bounce_reverses_registration(): void
    {
        $user = $this->env();
        $id = $this->incoming($user);
        $this->actingAs($user)->postJson("/api/cheques/{$id}/bounce")->assertOk()->assertJsonPath('data.status', 'BOUNCED');
        // قيد عكسي مُنشأ
        $this->assertTrue(JournalEntry::withoutGlobalScopes()->whereNotNull('reversed_entry_id')->exists());
    }

    public function test_outgoing_cheque_flow(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/cheques', [
            'type' => 'OUTGOING', 'fiscal_year_id' => $this->fy->id, 'cheque_number' => 'OUT1', 'amount' => 250,
            'issue_date' => '2026-02-01', 'due_date' => '2026-03-01',
            'party_account_id' => $this->acc['vendor']->id, 'bank_account_id' => $this->acc['bank']->id,
        ])->assertStatus(201)->json('data.id');
        $chq = Cheque::withoutGlobalScopes()->find($id);
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($chq->register_entry_id);
        // مدين المورد / دائن شيكات الدفع
        $this->assertSame('250.000', $entry->lines->firstWhere('account_id', $this->acc['vendor']->id)->debit);
        $this->assertSame('250.000', $entry->lines->firstWhere('account_id', $this->acc['payable']->id)->credit);
    }

    public function test_aging_report(): void
    {
        $user = $this->env();
        // حركة على العميل: مدين 300 بتاريخ قديم
        $e = JournalEntry::create(['company_id' => $this->company->id, 'fiscal_year_id' => $this->fy->id, 'type' => 'MANUAL',
            'entry_number' => 'JV1', 'entry_date' => '2026-01-01', 'status' => 'POSTED', 'total_debit' => 300, 'total_credit' => 300]);
        $e->lines()->create(['company_id' => $this->company->id, 'line_number' => 1, 'account_id' => $this->acc['customer']->id, 'debit' => 300, 'credit' => 0]);

        $res = $this->actingAs($user)->getJson('/api/reports/aging?type=CUSTOMER&as_of=2026-04-01')->assertOk();
        // 90 يوماً تقريباً ➜ في فئة d60 (61-90) والإجمالي 300
        $res->assertJsonPath('data.totals.total', 300);
    }
}
