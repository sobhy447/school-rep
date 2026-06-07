<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function env(): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $keys = ['accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete', 'audit.view'];
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        return User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'سعد', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
    }

    public function test_create_and_update_are_audited(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/accounts', ['code' => '100', 'name' => 'حساب', 'type' => 'ASSET'])->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'Account', 'auditable_id' => $id, 'action' => 'CREATE', 'user_name' => 'سعد']);

        $this->actingAs($user)->putJson("/api/accounts/{$id}", ['code' => '100', 'name' => 'حساب معدّل', 'type' => 'ASSET']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'Account', 'auditable_id' => $id, 'action' => 'UPDATE']);

        // السجل يعرض القيم الجديدة للتعديل
        $logs = $this->actingAs($user)->getJson('/api/audit-logs?action=UPDATE')->assertOk()->json('data');
        $this->assertSame('حساب معدّل', $logs[0]['new_values']['name']);
        $this->assertSame('حساب', $logs[0]['old_values']['name']);
    }

    public function test_audit_index_lists_logs(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/accounts', ['code' => '200', 'name' => 'x', 'type' => 'ASSET']);
        $this->actingAs($user)->getJson('/api/audit-logs')->assertOk()->assertJsonPath('data.0.action', 'CREATE');
    }

    public function test_tenant_isolation_on_audit(): void
    {
        $userA = $this->env();
        $this->actingAs($userA)->postJson('/api/accounts', ['code' => '300', 'name' => 'سرّي', 'type' => 'ASSET']);
        $userB = $this->env(); // شركة أخرى
        $this->actingAs($userB)->getJson('/api/audit-logs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_requires_permission(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->getJson('/api/audit-logs')->assertStatus(403);
    }
}
