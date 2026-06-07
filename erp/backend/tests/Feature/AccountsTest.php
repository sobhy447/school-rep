<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function makeUserWith(array $permissionKeys, string $prefix = 'u'): array
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'شركة', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'دور']);
        $ids = collect($permissionKeys)->map(fn ($k) => Permission::firstOrCreate(
            ['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k]
        )->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create([
            'company_id' => $company->id, 'role_id' => $role->id, 'name' => 'مستخدم',
            'email' => $prefix . uniqid() . '@test.test', 'password' => bcrypt('password'), 'is_active' => true,
        ]);
        return [$user, $company];
    }

    private function perms(): array
    {
        return ['accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete'];
    }

    public function test_asset_account_is_debit_and_balance_sheet(): void
    {
        [$user] = $this->makeUserWith($this->perms());

        $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'الأصول', 'type' => 'ASSET'])
            ->assertStatus(201)
            ->assertJsonPath('data.normal_balance', 'DEBIT')
            ->assertJsonPath('data.statement', 'BALANCE_SHEET');
    }

    public function test_revenue_account_is_credit_and_income_statement(): void
    {
        [$user] = $this->makeUserWith($this->perms());

        $this->actingAs($user)->postJson('/api/accounts', ['code' => '4', 'name' => 'إيرادات', 'type' => 'REVENUE'])
            ->assertStatus(201)
            ->assertJsonPath('data.normal_balance', 'CREDIT')
            ->assertJsonPath('data.statement', 'INCOME_STATEMENT');
    }

    public function test_child_type_must_match_parent(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $parent = $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'أصول', 'type' => 'ASSET'])->json('data.id');

        $this->actingAs($user)->postJson('/api/accounts', [
            'code' => '11', 'name' => 'خطأ', 'type' => 'LIABILITY', 'parent_id' => $parent,
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_creating_child_makes_parent_classification(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $parent = $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'أصول', 'type' => 'ASSET'])->json('data.id');
        $this->actingAs($user)->postJson('/api/accounts', ['code' => '11', 'name' => 'متداولة', 'type' => 'ASSET', 'parent_id' => $parent])->assertStatus(201);

        // الأب لم يعد ورقة
        $this->actingAs($user)->getJson("/api/accounts/{$parent}")->assertOk()->assertJsonPath('data.is_leaf', false);
    }

    public function test_cannot_delete_account_with_children(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $parent = $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'أصول', 'type' => 'ASSET'])->json('data.id');
        $this->actingAs($user)->postJson('/api/accounts', ['code' => '11', 'name' => 'ابن', 'type' => 'ASSET', 'parent_id' => $parent])->assertStatus(201);

        $this->actingAs($user)->deleteJson("/api/accounts/{$parent}")->assertStatus(422);
    }

    public function test_cycle_prevented_on_update(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $parent = $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'أب', 'type' => 'ASSET'])->json('data.id');
        $child = $this->actingAs($user)->postJson('/api/accounts', ['code' => '11', 'name' => 'ابن', 'type' => 'ASSET', 'parent_id' => $parent])->json('data.id');

        // محاولة جعل الأب تابعاً لابنه ➜ دائرة
        $this->actingAs($user)->putJson("/api/accounts/{$parent}", [
            'code' => '1', 'name' => 'أب', 'type' => 'ASSET', 'parent_id' => $child,
        ])->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
    }

    public function test_bottom_up_balance_rollup(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $parent = $this->actingAs($user)->postJson('/api/accounts', ['code' => '11', 'name' => 'متداولة', 'type' => 'ASSET'])->json('data.id');
        $this->actingAs($user)->postJson('/api/accounts', [
            'code' => '1101', 'name' => 'صندوق', 'type' => 'ASSET', 'parent_id' => $parent,
            'opening_balance' => 100, 'opening_balance_type' => 'DEBIT',
        ])->assertStatus(201);
        $this->actingAs($user)->postJson('/api/accounts', [
            'code' => '1102', 'name' => 'بنك', 'type' => 'ASSET', 'parent_id' => $parent,
            'opening_balance' => 40, 'opening_balance_type' => 'DEBIT',
        ])->assertStatus(201);

        $this->actingAs($user)->getJson("/api/accounts/{$parent}/balance")
            ->assertOk()->assertJsonPath('data.balance', 140);
    }

    public function test_liability_credit_opening_is_positive(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $id = $this->actingAs($user)->postJson('/api/accounts', [
            'code' => '21', 'name' => 'موردون', 'type' => 'LIABILITY',
            'opening_balance' => 250, 'opening_balance_type' => 'CREDIT',
        ])->json('data.id');

        $this->actingAs($user)->getJson("/api/accounts/{$id}/balance")
            ->assertOk()->assertJsonPath('data.balance', 250);
    }

    public function test_multi_category_assignment(): void
    {
        [$user, $company] = $this->makeUserWith($this->perms());
        $c1 = AccountCategory::withoutGlobalScopes()->create(['company_id' => $company->id, 'group' => 'customer_type', 'code' => 'VIP', 'name' => 'مميّز']);
        $c2 = AccountCategory::withoutGlobalScopes()->create(['company_id' => $company->id, 'group' => 'affiliation', 'code' => 'A', 'name' => 'فرع أ']);

        $id = $this->actingAs($user)->postJson('/api/accounts', [
            'code' => '1103', 'name' => 'عميل', 'type' => 'ASSET', 'category_ids' => [$c1->id, $c2->id],
            'meta' => ['phone' => '99000000', 'civil_id' => '290'],
        ])->assertStatus(201)->json('data.id');

        $show = $this->actingAs($user)->getJson("/api/accounts/{$id}")->assertOk();
        $show->assertJsonCount(2, 'data.categories');
        $show->assertJsonPath('data.meta.phone', '99000000');
    }

    public function test_duplicate_code_rejected_same_code_across_companies_ok(): void
    {
        [$userA] = $this->makeUserWith($this->perms(), 'a');
        [$userB] = $this->makeUserWith($this->perms(), 'b');

        $this->actingAs($userA)->postJson('/api/accounts', ['code' => '100', 'name' => 'x', 'type' => 'ASSET'])->assertStatus(201);
        $this->actingAs($userA)->postJson('/api/accounts', ['code' => '100', 'name' => 'y', 'type' => 'ASSET'])->assertStatus(422);
        // شركة أخرى بنفس الرمز
        $this->actingAs($userB)->postJson('/api/accounts', ['code' => '100', 'name' => 'z', 'type' => 'ASSET'])->assertStatus(201);
    }

    public function test_tenant_isolation_on_accounts(): void
    {
        [$userA, $companyA] = $this->makeUserWith($this->perms(), 'a');
        [$userB] = $this->makeUserWith($this->perms(), 'b');
        Account::withoutGlobalScopes()->create(['company_id' => $companyA->id, 'code' => '9', 'name' => 'سرّي', 'type' => 'ASSET']);

        $this->actingAs($userB)->getJson('/api/accounts')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($userA)->getJson('/api/accounts')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_import_dry_run_reports_errors(): void
    {
        [$user] = $this->makeUserWith($this->perms());

        $this->actingAs($user)->postJson('/api/accounts/import', [
            'commit' => false,
            'rows' => [
                ['code' => '1', 'name' => 'أصول', 'type' => 'ASSET'],
                ['code' => '', 'name' => 'بدون رمز', 'type' => 'ASSET'],
                ['code' => '2', 'name' => 'نوع خطأ', 'type' => 'WRONG'],
                ['code' => '3', 'name' => 'أب مفقود', 'type' => 'ASSET', 'parent_code' => 'ZZZ'],
            ],
        ])->assertStatus(422)
          ->assertJsonPath('data.imported', 0);
    }

    public function test_import_commit_builds_tree(): void
    {
        [$user] = $this->makeUserWith($this->perms());

        $res = $this->actingAs($user)->postJson('/api/accounts/import', [
            'commit' => true,
            'rows' => [
                // ترتيب غير تابعي عمداً (الابن قبل الأب) لاختبار حلّ الآباء
                ['code' => '1101', 'name' => 'صندوق', 'type' => 'ASSET', 'parent_code' => '11'],
                ['code' => '11', 'name' => 'متداولة', 'type' => 'ASSET', 'parent_code' => '1'],
                ['code' => '1', 'name' => 'أصول', 'type' => 'ASSET'],
            ],
        ])->assertOk();
        $res->assertJsonPath('data.imported', 3);

        $this->actingAs($user)->getJson('/api/accounts')->assertJsonCount(3, 'data');
        // الأب 1101 يجب أن يكون مرتبطاً بـ 11
        $accounts = Account::withoutGlobalScopes()->get()->keyBy('code');
        $this->assertSame($accounts['11']->id, $accounts['1101']->parent_id);
        $this->assertSame($accounts['1']->id, $accounts['11']->parent_id);
    }

    public function test_permission_enforced(): void
    {
        [$user] = $this->makeUserWith(['accounts.view']);
        $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'x', 'type' => 'ASSET'])
            ->assertStatus(403);
    }

    public function test_tree_endpoint_returns_nested(): void
    {
        [$user] = $this->makeUserWith($this->perms());
        $p = $this->actingAs($user)->postJson('/api/accounts', ['code' => '1', 'name' => 'أصول', 'type' => 'ASSET'])->json('data.id');
        $this->actingAs($user)->postJson('/api/accounts', ['code' => '11', 'name' => 'متداولة', 'type' => 'ASSET', 'parent_id' => $p])->assertStatus(201);

        $this->actingAs($user)->getJson('/api/accounts/tree')
            ->assertOk()
            ->assertJsonPath('data.0.code', '1')
            ->assertJsonPath('data.0.children.0.code', '11');
    }
}
