<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function env(): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $keys = [];
        foreach (['reminders'] as $m) foreach (['view', 'create', 'edit', 'delete'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => 'reminders', 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        return User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
    }

    public function test_create_manual_reminder(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/reminders', ['title' => 'تجديد رخصة', 'due_date' => '2026-07-01'])
            ->assertStatus(201)->assertJsonPath('data.status', 'PENDING')->assertJsonPath('data.type', 'CUSTOM');
    }

    public function test_generate_reorder_reminder(): void
    {
        $user = $this->env();
        InventoryItem::create(['company_id' => $this->company->id, 'code' => 'IT1', 'name' => 'صنف', 'reorder_level' => 5]); // المتاح 0 ≤ 5

        $this->actingAs($user)->postJson('/api/reminders/generate')->assertOk()->assertJsonPath('data.created', 1);
        // التوليد مرة أخرى لا يكرّر
        $this->actingAs($user)->postJson('/api/reminders/generate')->assertOk()->assertJsonPath('data.created', 0);

        $this->actingAs($user)->getJson('/api/reminders/due')->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_mark_done(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/reminders', ['title' => 'متابعة', 'due_date' => '2026-06-10'])->json('data.id');
        $this->actingAs($user)->postJson("/api/reminders/{$id}/done")->assertOk()->assertJsonPath('data.status', 'DONE');
        // لم يعد ضمن المستحقّة
        $this->actingAs($user)->getJson('/api/reminders/due')->assertJsonPath('data.count', 0);
    }

    public function test_permission_enforced(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $role->permissions()->sync([Permission::firstOrCreate(['key' => 'reminders.view'], ['module' => 'reminders', 'label_ar' => 'v', 'label_en' => 'v'])->id]);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->postJson('/api/reminders', ['title' => 'x', 'due_date' => '2026-06-10'])->assertStatus(403);
    }
}
