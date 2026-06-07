<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /** ينشئ شركة + مستخدم بصلاحيات محددة، ويرجّع [user, company]. */
    private function makeUserWith(array $permissionKeys, string $emailPrefix = 'u'): array
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'شركة', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'دور']);

        $ids = collect($permissionKeys)->map(function ($key) {
            return Permission::firstOrCreate(
                ['key' => $key],
                ['module' => explode('.', $key)[0], 'label_ar' => $key, 'label_en' => $key]
            )->id;
        })->all();
        $role->permissions()->sync($ids);

        $user = User::create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'مستخدم',
            'email' => $emailPrefix . uniqid() . '@test.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        return [$user, $company];
    }

    private function allSettingsPerms(): array
    {
        return ['settings.view', 'settings.create', 'settings.edit', 'settings.delete', 'settings.lock'];
    }

    public function test_unauthenticated_cannot_access_settings(): void
    {
        $this->getJson('/api/settings/branches')->assertStatus(401);
    }

    public function test_can_create_and_list_branch(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $this->actingAs($user)->postJson('/api/settings/branches', [
            'code' => 'B1', 'name' => 'فرع تجريبي',
        ])->assertStatus(201)->assertJsonPath('data.code', 'B1');

        $this->actingAs($user)->getJson('/api/settings/branches')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_create_branch_requires_code_and_name(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $this->actingAs($user)->postJson('/api/settings/branches', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_duplicate_code_rejected_within_company(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $this->actingAs($user)->postJson('/api/settings/branches', ['code' => 'DUP', 'name' => 'أول'])->assertStatus(201);
        $this->actingAs($user)->postJson('/api/settings/branches', ['code' => 'DUP', 'name' => 'تاني'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_same_code_allowed_in_different_companies(): void
    {
        [$userA] = $this->makeUserWith($this->allSettingsPerms(), 'a');
        [$userB] = $this->makeUserWith($this->allSettingsPerms(), 'b');

        $this->actingAs($userA)->postJson('/api/settings/branches', ['code' => 'SAME', 'name' => 'A'])->assertStatus(201);
        $this->actingAs($userB)->postJson('/api/settings/branches', ['code' => 'SAME', 'name' => 'B'])->assertStatus(201);
    }

    public function test_tenant_isolation_on_listing(): void
    {
        [$userA, $companyA] = $this->makeUserWith($this->allSettingsPerms(), 'a');
        [$userB] = $this->makeUserWith($this->allSettingsPerms(), 'b');

        // فرع للشركة A فقط
        Branch::withoutGlobalScopes()->create(['company_id' => $companyA->id, 'code' => 'X', 'name' => 'سرّي']);

        // B لا يرى شيئاً
        $this->actingAs($userB)->getJson('/api/settings/branches')->assertOk()->assertJsonCount(0, 'data');
        // A يرى فرعه
        $this->actingAs($userA)->getJson('/api/settings/branches')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cannot_show_other_company_record(): void
    {
        [$userA, $companyA] = $this->makeUserWith($this->allSettingsPerms(), 'a');
        [$userB] = $this->makeUserWith($this->allSettingsPerms(), 'b');

        $branch = Branch::withoutGlobalScopes()->create(['company_id' => $companyA->id, 'code' => 'P', 'name' => 'خاص']);

        // B يحاول الوصول لسجل A → 404 (محجوب بالعزل)
        $this->actingAs($userB)->getJson("/api/settings/branches/{$branch->id}")->assertStatus(404);
    }

    public function test_permission_enforced_for_create(): void
    {
        // مستخدم له عرض فقط بدون إنشاء
        [$user] = $this->makeUserWith(['settings.view']);

        $this->actingAs($user)->postJson('/api/settings/branches', ['code' => 'B', 'name' => 'x'])
            ->assertStatus(403);
    }

    public function test_fiscal_year_lock_and_unlock(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $res = $this->actingAs($user)->postJson('/api/settings/fiscal-years', [
            'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ])->assertStatus(201);
        $id = $res->json('data.id');

        $this->actingAs($user)->postJson("/api/settings/fiscal-years/{$id}/lock")
            ->assertOk()->assertJsonPath('data.is_locked', true)->assertJsonPath('data.status', 'CLOSED');

        $this->actingAs($user)->postJson("/api/settings/fiscal-years/{$id}/unlock")
            ->assertOk()->assertJsonPath('data.is_locked', false)->assertJsonPath('data.status', 'OPEN');
    }

    public function test_fiscal_year_end_must_be_after_start(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $this->actingAs($user)->postJson('/api/settings/fiscal-years', [
            'name' => 'bad', 'start_date' => '2026-12-31', 'end_date' => '2026-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    public function test_only_one_base_currency_per_company(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $this->actingAs($user)->postJson('/api/settings/currencies', [
            'code' => 'KWD', 'name' => 'دينار', 'is_base' => true,
        ])->assertStatus(201);

        $this->actingAs($user)->postJson('/api/settings/currencies', [
            'code' => 'USD', 'name' => 'دولار', 'is_base' => true, 'exchange_rate' => 0.3,
        ])->assertStatus(201);

        // العملة الأساسية الوحيدة الآن هي USD
        $bases = $this->actingAs($user)->getJson('/api/settings/currencies')->json('data');
        $baseCodes = collect($bases)->where('is_base', true)->pluck('code')->all();
        $this->assertSame(['USD'], $baseCodes);
    }

    public function test_voucher_type_direction_validated(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $this->actingAs($user)->postJson('/api/settings/voucher-types', [
            'code' => 'VT', 'name' => 'نوع', 'direction' => 'INVALID',
        ])->assertStatus(422)->assertJsonValidationErrors(['direction']);
    }

    public function test_update_and_delete_branch(): void
    {
        [$user] = $this->makeUserWith($this->allSettingsPerms());

        $id = $this->actingAs($user)->postJson('/api/settings/branches', ['code' => 'E', 'name' => 'قديم'])
            ->json('data.id');

        $this->actingAs($user)->putJson("/api/settings/branches/{$id}", ['code' => 'E', 'name' => 'جديد'])
            ->assertOk()->assertJsonPath('data.name', 'جديد');

        $this->actingAs($user)->deleteJson("/api/settings/branches/{$id}")->assertOk();
        $this->actingAs($user)->getJson('/api/settings/branches')->assertJsonCount(0, 'data');
    }
}
