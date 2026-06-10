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

class BudgetTest extends TestCase
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
        foreach (['budgets', 'reports', 'accounts'] as $m) foreach (['view', 'create'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($c, $nm, $t) => Account::create(['company_id' => $cid, 'code' => $c, 'name' => $nm, 'type' => $t]);
        $this->acc['exp'] = $mk('51', 'مصروفات', 'EXPENSE');
        $this->acc['rev'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['cash'] = $mk('1101', 'الصندوق', 'ASSET');
        return $user;
    }

    private function posted(array $lines, string $date = '2026-03-01'): void
    {
        $cid = $this->company->id;
        $e = JournalEntry::create(['company_id' => $cid, 'fiscal_year_id' => $this->fy->id, 'type' => 'MANUAL',
            'entry_number' => 'JV' . uniqid(), 'entry_date' => $date, 'status' => 'POSTED',
            'total_debit' => array_sum(array_column($lines, 'debit')), 'total_credit' => array_sum(array_column($lines, 'credit'))]);
        foreach ($lines as $i => $l) {
            $e->lines()->create(['company_id' => $cid, 'line_number' => $i + 1, 'account_id' => $l['account_id'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0]);
        }
    }

    public function test_budget_vs_actual(): void
    {
        $user = $this->env();
        // موازنة مصروفات 1000
        $this->actingAs($user)->postJson('/api/budgets', ['fiscal_year_id' => $this->fy->id, 'account_id' => $this->acc['exp']->id, 'amount' => 1000])->assertOk();
        // فعلي: مصروف 400
        $this->posted([['account_id' => $this->acc['exp']->id, 'debit' => 400], ['account_id' => $this->acc['cash']->id, 'credit' => 400]]);

        $res = $this->actingAs($user)->getJson("/api/reports/budget?fiscal_year_id={$this->fy->id}")->assertOk();
        $res->assertJsonPath('data.rows.0.budget', 1000)
            ->assertJsonPath('data.rows.0.actual', 400)
            ->assertJsonPath('data.rows.0.variance', 600)
            ->assertJsonPath('data.rows.0.used_pct', 40);
    }

    public function test_budget_upsert_updates(): void
    {
        $user = $this->env();
        $p = ['fiscal_year_id' => $this->fy->id, 'account_id' => $this->acc['exp']->id, 'amount' => 500];
        $this->actingAs($user)->postJson('/api/budgets', $p)->assertOk();
        $this->actingAs($user)->postJson('/api/budgets', array_merge($p, ['amount' => 800]))->assertOk();
        $this->assertSame(1, \App\Models\Budget::withoutGlobalScopes()->where('account_id', $this->acc['exp']->id)->count());
        $this->assertSame('800.000', \App\Models\Budget::withoutGlobalScopes()->where('account_id', $this->acc['exp']->id)->value('amount'));
    }

    public function test_monthly_trend(): void
    {
        $user = $this->env();
        $this->posted([['account_id' => $this->acc['cash']->id, 'debit' => 700], ['account_id' => $this->acc['rev']->id, 'credit' => 700]], '2026-03-15');
        $this->posted([['account_id' => $this->acc['exp']->id, 'debit' => 200], ['account_id' => $this->acc['cash']->id, 'credit' => 200]], '2026-05-10');

        $res = $this->actingAs($user)->getJson('/api/reports/monthly-trend?year=2026')->assertOk();
        $months = collect($res->json('data.months'));
        $this->assertSame(700.0, (float) $months->firstWhere('month', 3)['revenue']);
        $this->assertSame(200.0, (float) $months->firstWhere('month', 5)['expense']);
    }

    public function test_permission_enforced(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->postJson('/api/budgets', ['fiscal_year_id' => 1, 'account_id' => 1, 'amount' => 1])->assertStatus(403);
    }
}
