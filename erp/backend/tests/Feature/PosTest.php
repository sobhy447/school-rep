<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\FiscalYear;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private FiscalYear $fy;
    private Warehouse $wh;
    private InventoryItem $item;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function env(): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $keys = [];
        foreach (['pos', 'accounts'] as $m) foreach (['view', 'create'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($code, $name, $type, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $o));
        $this->acc['cash'] = $mk('1101', 'الصندوق', 'ASSET', ['is_cash_or_bank' => true]);
        $this->acc['inv'] = $mk('1105', 'المخزون', 'ASSET');
        $this->acc['rev'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['cogs'] = $mk('53', 'تكلفة', 'EXPENSE');
        $this->acc['vatout'] = $mk('22', 'ضريبة مخرجات', 'LIABILITY');
        CompanySetting::put($cid, 'vat_output_account_id', (string) $this->acc['vatout']->id);
        $this->wh = Warehouse::create(['company_id' => $cid, 'code' => 'W1', 'name' => 'مخزن']);
        $this->item = InventoryItem::create(['company_id' => $cid, 'code' => 'IT1', 'name' => 'صنف',
            'inventory_account_id' => $this->acc['inv']->id, 'cogs_account_id' => $this->acc['cogs']->id, 'revenue_account_id' => $this->acc['rev']->id]);
        app(InventoryService::class)->receive($this->item, $this->wh->id, 10, 10, '2026-01-01');
        return $user;
    }

    public function test_checkout_posts_cash_sale_and_reduces_stock(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/pos/checkout', [
            'fiscal_year_id' => $this->fy->id, 'warehouse_id' => $this->wh->id, 'cash_account_id' => $this->acc['cash']->id,
            'sale_date' => '2026-02-01', 'paid' => 70,
            'lines' => [['item_id' => $this->item->id, 'quantity' => 3, 'unit_price' => 20, 'tax_rate' => 5]],
        ])->assertStatus(201);

        // subtotal 60 + ضريبة 3 = 63، الباقي 7
        $res->assertJsonPath('data.total', '63.000')->assertJsonPath('data.change_amount', '7.000');
        $this->assertMatchesRegularExpression('/POS-2026-\d{5}/', $res->json('data.sale_number'));

        // المخزون نقص لـ 7
        $this->assertSame(7.0, app(InventoryService::class)->totalOnHand($this->item->id));

        // القيد: مدين صندوق 63 + تكلفة 30 = دائن إيراد 60 + ضريبة 3 + مخزون 30 = 93
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.journal_entry_id'));
        $this->assertSame('93.000', $entry->total_debit);
        $this->assertSame('93.000', $entry->total_credit);
        $this->assertSame('63.000', $entry->lines->firstWhere('account_id', $this->acc['cash']->id)->debit);
        $this->assertSame('30.000', $entry->lines->firstWhere('account_id', $this->acc['cogs']->id)->debit);
    }

    public function test_cannot_sell_more_than_stock(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/pos/checkout', [
            'fiscal_year_id' => $this->fy->id, 'warehouse_id' => $this->wh->id, 'cash_account_id' => $this->acc['cash']->id,
            'sale_date' => '2026-02-01', 'lines' => [['item_id' => $this->item->id, 'quantity' => 50, 'unit_price' => 20]],
        ])->assertStatus(422);
    }

    public function test_permission_enforced(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $role->permissions()->sync([Permission::firstOrCreate(['key' => 'pos.view'], ['module' => 'pos', 'label_ar' => 'v', 'label_en' => 'v'])->id]);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->postJson('/api/pos/checkout', ['lines' => []])->assertStatus(403);
    }
}
