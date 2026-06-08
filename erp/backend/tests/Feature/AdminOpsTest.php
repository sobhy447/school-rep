<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminOpsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function admin(array $perms, bool $super = false): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'admin', 'name' => 'مدير']);
        $ids = collect($perms)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        return User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'الأدمن', 'email' => 'a' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true, 'is_super' => $super]);
    }

    public function test_admin_creates_role_and_user(): void
    {
        $user = $this->admin(['users.view', 'users.create', 'accounts.view', 'accounts.create']);
        // إنشاء دور بصلاحيات
        $roleId = $this->actingAs($user)->postJson('/api/roles', ['name' => 'محاسب', 'slug' => 'acc', 'permissions' => ['accounts.view', 'accounts.create']])
            ->assertStatus(201)->json('data.id');
        $this->actingAs($user)->getJson("/api/roles/{$roleId}")->assertOk()->assertJsonPath('data.permissions.0', 'accounts.view');

        // إنشاء مستخدم بهذا الدور
        $this->actingAs($user)->postJson('/api/users', ['name' => 'موظف', 'email' => 'emp' . uniqid() . '@t.test', 'password' => 'secret1', 'role_id' => $roleId])
            ->assertStatus(201);

        // المستخدم الجديد يقدر يدخل
        $newEmail = User::where('name', 'موظف')->first()->email;
        $this->postJson('/api/login', ['email' => $newEmail, 'password' => 'secret1'])->assertOk();
    }

    public function test_permissions_list_grouped(): void
    {
        $user = $this->admin(['users.view', 'accounts.view', 'sales.view']);
        $this->actingAs($user)->getJson('/api/roles/permissions')->assertOk()
            ->assertJsonStructure(['data' => [['module', 'items']]]);
    }

    public function test_users_management_requires_permission(): void
    {
        $user = $this->admin(['accounts.view']); // بدون users.*
        $this->actingAs($user)->getJson('/api/users')->assertStatus(403);
    }

    public function test_super_creates_and_switches_company(): void
    {
        $user = $this->admin(['settings.view', 'settings.edit'], super: true);
        // إنشاء شركة جديدة مُجهَّزة
        $res = $this->actingAs($user)->postJson('/api/companies', [
            'code' => 'NEWCO', 'name' => 'شركة جديدة', 'admin_name' => 'مدير', 'admin_email' => 'newadmin@t.test', 'admin_password' => 'pass123',
        ])->assertStatus(201);
        $newId = $res->json('data.id');

        // الشركة اتجهّزت: حسابات + مدير
        $this->assertGreaterThan(0, Account::withoutGlobalScopes()->where('company_id', $newId)->count());
        $this->assertDatabaseHas('users', ['email' => 'newadmin@t.test', 'company_id' => $newId]);

        // التبديل للشركة الجديدة ➜ /me يعكسها
        $this->actingAs($user)->postJson("/api/companies/{$newId}/switch")->assertOk();
        $this->actingAs($user->fresh())->getJson('/api/me')->assertOk()->assertJsonPath('data.company.id', $newId);
    }

    public function test_non_super_cannot_create_company(): void
    {
        $user = $this->admin(['settings.view', 'settings.edit'], super: false);
        $this->actingAs($user)->postJson('/api/companies', [
            'code' => 'X', 'name' => 'x', 'admin_name' => 'a', 'admin_email' => 'z@t.test', 'admin_password' => 'pass123',
        ])->assertStatus(422);
    }

    public function test_bank_statement_csv_import_matches(): void
    {
        $user = $this->admin(['banks.view', 'banks.create', 'banks.edit', 'accounts.view']);
        $cid = $this->company->id;
        $fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $bank = Account::create(['company_id' => $cid, 'code' => '1102', 'name' => 'البنك', 'type' => 'ASSET', 'is_cash_or_bank' => true]);
        $rev = Account::create(['company_id' => $cid, 'code' => '41', 'name' => 'إيراد', 'type' => 'REVENUE']);
        // حركة بنكية مُرحَّلة: البنك مدين 500
        $e = JournalEntry::create(['company_id' => $cid, 'fiscal_year_id' => $fy->id, 'type' => 'MANUAL', 'entry_number' => 'JV1', 'entry_date' => '2026-02-01', 'status' => 'POSTED', 'total_debit' => 500, 'total_credit' => 500]);
        $e->lines()->create(['company_id' => $cid, 'line_number' => 1, 'account_id' => $bank->id, 'debit' => 500, 'credit' => 0]);
        $e->lines()->create(['company_id' => $cid, 'line_number' => 2, 'account_id' => $rev->id, 'debit' => 0, 'credit' => 500]);

        $recId = $this->actingAs($user)->postJson('/api/bank-reconciliations', ['account_id' => $bank->id, 'statement_date' => '2026-02-28', 'statement_balance' => 500])
            ->json('data.reconciliation.id');

        $csv = UploadedFile::fake()->createWithContent('stmt.csv', "date,amount\n2026-02-01,500\n");
        $res = $this->actingAs($user)->post("/api/bank-reconciliations/{$recId}/import-statement", ['file' => $csv]);
        $res->assertOk()->assertJsonPath('data.cleared_balance', 500)->assertJsonPath('data.difference', 0);
    }
}
