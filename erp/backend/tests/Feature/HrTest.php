<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrTest extends TestCase
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
        foreach (['hr', 'accounts'] as $m) foreach (['view', 'create', 'edit', 'delete', 'post'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($code, $name, $type) => Account::create(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type]);
        $this->acc['exp'] = $mk('54', 'مصروف رواتب', 'EXPENSE');
        $this->acc['pay'] = $mk('23', 'رواتب مستحقة', 'LIABILITY');
        $this->acc['ded'] = $mk('24', 'استقطاعات مستحقة', 'LIABILITY');
        CompanySetting::put($cid, 'salary_expense_account_id', (string) $this->acc['exp']->id);
        CompanySetting::put($cid, 'salaries_payable_account_id', (string) $this->acc['pay']->id);
        CompanySetting::put($cid, 'deductions_payable_account_id', (string) $this->acc['ded']->id);
        return $user;
    }

    public function test_create_employee_and_components(): void
    {
        $user = $this->env();
        $empId = $this->actingAs($user)->postJson('/api/employees', ['code' => 'E1', 'name' => 'أحمد', 'basic_salary' => 500])->assertStatus(201)->json('data.id');
        $comp = $this->actingAs($user)->postJson('/api/salary-components', ['code' => 'H', 'name' => 'بدل', 'type' => 'EARNING'])->assertStatus(201)->json('data.id');
        $this->actingAs($user)->postJson("/api/employees/{$empId}/components", ['components' => [['component_id' => $comp, 'amount' => 100]]])
            ->assertOk()->assertJsonCount(1, 'data.components');
    }

    public function test_generate_payroll_computes_net(): void
    {
        $user = $this->env();
        $earn = SalaryComponent::create(['company_id' => $this->company->id, 'code' => 'H', 'name' => 'بدل', 'type' => 'EARNING']);
        $ded = SalaryComponent::create(['company_id' => $this->company->id, 'code' => 'G', 'name' => 'تأمين', 'type' => 'DEDUCTION']);
        $emp = Employee::create(['company_id' => $this->company->id, 'code' => 'E1', 'name' => 'أحمد', 'basic_salary' => 500, 'is_active' => true]);
        $emp->components()->create(['company_id' => $this->company->id, 'component_id' => $earn->id, 'amount' => 100]);
        $emp->components()->create(['company_id' => $this->company->id, 'component_id' => $ded->id, 'amount' => 50]);

        $res = $this->actingAs($user)->postJson('/api/payroll/generate', [
            'fiscal_year_id' => $this->fy->id, 'period_year' => 2026, 'period_month' => 1, 'run_date' => '2026-01-31',
        ])->assertStatus(201);
        // إجمالي 600، استقطاع 50، صافي 550
        $res->assertJsonPath('data.total_earnings', '600.000')
            ->assertJsonPath('data.total_deductions', '50.000')
            ->assertJsonPath('data.net_total', '550.000');
    }

    public function test_post_payroll_creates_balanced_entry(): void
    {
        $user = $this->env();
        $earn = SalaryComponent::create(['company_id' => $this->company->id, 'code' => 'H', 'name' => 'بدل', 'type' => 'EARNING']);
        $ded = SalaryComponent::create(['company_id' => $this->company->id, 'code' => 'G', 'name' => 'تأمين', 'type' => 'DEDUCTION']);
        $emp = Employee::create(['company_id' => $this->company->id, 'code' => 'E1', 'name' => 'أحمد', 'basic_salary' => 500, 'is_active' => true]);
        $emp->components()->create(['company_id' => $this->company->id, 'component_id' => $earn->id, 'amount' => 100]);
        $emp->components()->create(['company_id' => $this->company->id, 'component_id' => $ded->id, 'amount' => 50]);

        $runId = $this->actingAs($user)->postJson('/api/payroll/generate', ['fiscal_year_id' => $this->fy->id, 'period_year' => 2026, 'period_month' => 1, 'run_date' => '2026-01-31'])->json('data.id');
        $res = $this->actingAs($user)->postJson("/api/payroll/{$runId}/post")->assertOk();
        $res->assertJsonPath('data.status', 'POSTED');

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.journal_entry_id'));
        $this->assertSame('600.000', $entry->total_debit);
        $this->assertSame('600.000', $entry->total_credit);
        $this->assertSame('600.000', $entry->lines->firstWhere('account_id', $this->acc['exp']->id)->debit);
        $this->assertSame('550.000', $entry->lines->firstWhere('account_id', $this->acc['pay']->id)->credit);
        $this->assertSame('50.000', $entry->lines->firstWhere('account_id', $this->acc['ded']->id)->credit);
    }

    public function test_cannot_generate_same_period_twice(): void
    {
        $user = $this->env();
        Employee::create(['company_id' => $this->company->id, 'code' => 'E1', 'name' => 'أحمد', 'basic_salary' => 500, 'is_active' => true]);
        $p = ['fiscal_year_id' => $this->fy->id, 'period_year' => 2026, 'period_month' => 1, 'run_date' => '2026-01-31'];
        $this->actingAs($user)->postJson('/api/payroll/generate', $p)->assertStatus(201);
        $this->actingAs($user)->postJson('/api/payroll/generate', $p)->assertStatus(422);
    }

    public function test_permission_enforced(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $role->permissions()->sync([Permission::firstOrCreate(['key' => 'hr.view'], ['module' => 'hr', 'label_ar' => 'v', 'label_en' => 'v'])->id]);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->postJson('/api/employees', ['code' => 'X', 'name' => 'y'])->assertStatus(403);
    }
}
