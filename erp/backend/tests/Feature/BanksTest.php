<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BanksTest extends TestCase
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
        foreach (['banks', 'accounts'] as $m) foreach (['view', 'create', 'edit'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($code, $name, $type, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $o));
        $this->acc['bank'] = $mk('1102', 'البنك', 'ASSET', ['is_cash_or_bank' => true]);
        $this->acc['rev'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['exp'] = $mk('51', 'مصروفات', 'EXPENSE');
        return $user;
    }

    private function postedEntry(array $lines): JournalEntry
    {
        $cid = $this->company->id;
        $entry = JournalEntry::create([
            'company_id' => $cid, 'fiscal_year_id' => $this->fy->id, 'type' => 'MANUAL',
            'entry_number' => 'JV-' . uniqid(), 'entry_date' => '2026-02-01', 'status' => 'POSTED',
            'total_debit' => round(array_sum(array_column($lines, 'debit')), 3),
            'total_credit' => round(array_sum(array_column($lines, 'credit')), 3),
        ]);
        foreach ($lines as $i => $l) {
            $entry->lines()->create(['company_id' => $cid, 'line_number' => $i + 1, 'account_id' => $l['account_id'],
                'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0]);
        }
        return $entry->load('lines');
    }

    public function test_create_reconciliation_loads_bank_lines(): void
    {
        $user = $this->env();
        // إيداع 500 (بنك مدين) + سحب 200 (بنك دائن)
        $this->postedEntry([['account_id' => $this->acc['bank']->id, 'debit' => 500], ['account_id' => $this->acc['rev']->id, 'credit' => 500]]);
        $this->postedEntry([['account_id' => $this->acc['exp']->id, 'debit' => 200], ['account_id' => $this->acc['bank']->id, 'credit' => 200]]);

        $res = $this->actingAs($user)->postJson('/api/bank-reconciliations', [
            'account_id' => $this->acc['bank']->id, 'statement_date' => '2026-02-28', 'statement_balance' => 300,
        ])->assertStatus(201);
        $res->assertJsonCount(2, 'data.lines')          // حركتان على البنك
            ->assertJsonPath('data.cleared_balance', 0)  // لا شيء مؤشّر بعد
            ->assertJsonPath('data.difference', 300);
    }

    public function test_toggle_clears_and_reconciles(): void
    {
        $user = $this->env();
        $this->postedEntry([['account_id' => $this->acc['bank']->id, 'debit' => 500], ['account_id' => $this->acc['rev']->id, 'credit' => 500]]);
        $this->postedEntry([['account_id' => $this->acc['exp']->id, 'debit' => 200], ['account_id' => $this->acc['bank']->id, 'credit' => 200]]);

        $rec = $this->actingAs($user)->postJson('/api/bank-reconciliations', [
            'account_id' => $this->acc['bank']->id, 'statement_date' => '2026-02-28', 'statement_balance' => 300,
        ])->json('data');
        $recId = $rec['reconciliation']['id'];
        $lineIds = collect($rec['lines'])->pluck('line_id');

        // أشّر الحركتين ➜ المُسوّى 300، الفرق 0
        $this->actingAs($user)->postJson("/api/bank-reconciliations/{$recId}/toggle", ['line_id' => $lineIds[0], 'cleared' => true]);
        $v = $this->actingAs($user)->postJson("/api/bank-reconciliations/{$recId}/toggle", ['line_id' => $lineIds[1], 'cleared' => true])->assertOk();
        $v->assertJsonPath('data.cleared_balance', 300)->assertJsonPath('data.difference', 0)->assertJsonPath('data.is_reconciled', true);

        $this->actingAs($user)->postJson("/api/bank-reconciliations/{$recId}/complete")->assertOk()
            ->assertJsonPath('data.reconciliation.status', 'COMPLETED');
    }

    public function test_complete_fails_when_unbalanced(): void
    {
        $user = $this->env();
        $this->postedEntry([['account_id' => $this->acc['bank']->id, 'debit' => 500], ['account_id' => $this->acc['rev']->id, 'credit' => 500]]);

        $rec = $this->actingAs($user)->postJson('/api/bank-reconciliations', [
            'account_id' => $this->acc['bank']->id, 'statement_date' => '2026-02-28', 'statement_balance' => 999,
        ])->json('data');
        $recId = $rec['reconciliation']['id'];

        $this->actingAs($user)->postJson("/api/bank-reconciliations/{$recId}/complete")->assertStatus(422);
    }

    public function test_non_bank_account_rejected(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/bank-reconciliations', [
            'account_id' => $this->acc['rev']->id, 'statement_date' => '2026-02-28', 'statement_balance' => 0,
        ])->assertStatus(422);
    }
}
