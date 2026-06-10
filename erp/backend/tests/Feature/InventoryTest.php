<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
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
        foreach (['inventory'] as $m) foreach (['view', 'create', 'edit', 'delete'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => 'inventory', 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        return User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
    }

    private function warehouse(string $code = 'W1'): Warehouse
    {
        return Warehouse::create(['company_id' => $this->company->id, 'code' => $code, 'name' => $code]);
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create(['company_id' => $this->company->id, 'code' => 'I' . uniqid(), 'name' => 'صنف']);
    }

    public function test_create_warehouse_and_item(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/warehouses', ['code' => 'WX', 'name' => 'مخزن'])->assertStatus(201);
        $this->actingAs($user)->postJson('/api/items', ['code' => 'IX', 'name' => 'صنف'])->assertStatus(201);
    }

    public function test_receive_updates_weighted_average(): void
    {
        $user = $this->env();
        $w = $this->warehouse(); $item = $this->item();
        // استلام 10 بسعر 10 ثم 10 بسعر 20 ➜ المتوسط 15، الرصيد 20
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 10, 'unit_cost' => 10, 'movement_date' => '2026-01-01'])->assertOk();
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 10, 'unit_cost' => 20, 'movement_date' => '2026-01-02'])->assertOk();

        $this->actingAs($user)->getJson("/api/stock/item/{$item->id}")->assertOk()
            ->assertJsonPath('data.average_cost', 15)
            ->assertJsonPath('data.total', 20);
    }

    public function test_issue_at_average_and_reduces_stock(): void
    {
        $user = $this->env();
        $w = $this->warehouse(); $item = $this->item();
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 10, 'unit_cost' => 10, 'movement_date' => '2026-01-01']);
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 10, 'unit_cost' => 20, 'movement_date' => '2026-01-02']);

        $res = $this->actingAs($user)->postJson('/api/stock/issue', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 5, 'movement_date' => '2026-01-03'])->assertOk();
        $this->assertSame('15.000', $res->json('data.unit_cost')); // بتكلفة المتوسط
        $this->actingAs($user)->getJson("/api/stock/item/{$item->id}")->assertJsonPath('data.total', 15);
    }

    public function test_issue_more_than_available_rejected(): void
    {
        $user = $this->env();
        $w = $this->warehouse(); $item = $this->item();
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 5, 'unit_cost' => 10, 'movement_date' => '2026-01-01']);
        $this->actingAs($user)->postJson('/api/stock/issue', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 10, 'movement_date' => '2026-01-02'])->assertStatus(422);
    }

    public function test_transfer_between_warehouses(): void
    {
        $user = $this->env();
        $a = $this->warehouse('A'); $b = $this->warehouse('B'); $item = $this->item();
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $a->id, 'quantity' => 10, 'unit_cost' => 10, 'movement_date' => '2026-01-01']);
        $this->actingAs($user)->postJson('/api/stock/transfer', ['item_id' => $item->id, 'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id, 'quantity' => 4, 'movement_date' => '2026-01-02'])->assertOk();

        $stock = $this->actingAs($user)->getJson("/api/stock/item/{$item->id}")->json('data');
        $byWh = collect($stock['warehouses'])->keyBy('warehouse');
        $this->assertSame(6.0, (float) $byWh['A']['quantity']);
        $this->assertSame(4.0, (float) $byWh['B']['quantity']);
        $this->assertSame(10.0, (float) $stock['total']);
    }

    public function test_adjust_shortage_beyond_available_rejected(): void
    {
        $user = $this->env();
        $w = $this->warehouse(); $item = $this->item();
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 5, 'unit_cost' => 10, 'movement_date' => '2026-01-01']);
        $this->actingAs($user)->postJson('/api/stock/adjust', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'delta' => -10, 'movement_date' => '2026-01-02'])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/stock/adjust', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'delta' => -2, 'movement_date' => '2026-01-02'])->assertOk();
        $this->actingAs($user)->getJson("/api/stock/item/{$item->id}")->assertJsonPath('data.total', 3);
    }

    public function test_valuation(): void
    {
        $user = $this->env();
        $w = $this->warehouse(); $item = $this->item();
        $this->actingAs($user)->postJson('/api/stock/receive', ['item_id' => $item->id, 'warehouse_id' => $w->id, 'quantity' => 8, 'unit_cost' => 25, 'movement_date' => '2026-01-01']);
        $this->actingAs($user)->getJson('/api/stock/valuation')->assertOk()
            ->assertJsonPath('data.total_value', 200); // 8 * 25
    }

    public function test_permission_enforced(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $role->permissions()->sync([Permission::firstOrCreate(['key' => 'inventory.view'], ['module' => 'inventory', 'label_ar' => 'v', 'label_en' => 'v'])->id]);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->postJson('/api/items', ['code' => 'X', 'name' => 'y'])->assertStatus(403);
    }
}
