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

class TradeTest extends TestCase
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
        foreach (['purchases', 'sales', 'inventory', 'accounts', 'reports'] as $m) foreach (['view', 'create', 'edit', 'post'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($code, $name, $type, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $o));
        $this->acc['inv'] = $mk('1105', 'المخزون', 'ASSET');
        $this->acc['vatin'] = $mk('1106', 'ضريبة المدخلات', 'ASSET');
        $this->acc['vendor'] = $mk('21', 'الموردون', 'LIABILITY', ['party_type' => 'VENDOR']);
        $this->acc['vatout'] = $mk('22', 'ضريبة المخرجات', 'LIABILITY');
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['cogs'] = $mk('53', 'تكلفة البضاعة', 'EXPENSE');
        CompanySetting::put($cid, 'vat_input_account_id', (string) $this->acc['vatin']->id);
        CompanySetting::put($cid, 'vat_output_account_id', (string) $this->acc['vatout']->id);

        $this->wh = Warehouse::create(['company_id' => $cid, 'code' => 'W1', 'name' => 'مخزن']);
        $this->item = InventoryItem::create(['company_id' => $cid, 'code' => 'IT1', 'name' => 'صنف',
            'inventory_account_id' => $this->acc['inv']->id, 'cogs_account_id' => $this->acc['cogs']->id, 'revenue_account_id' => $this->acc['revenue']->id]);
        return $user;
    }

    public function test_purchase_invoice_posts_stock_and_balanced_entry(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/purchase-invoices', [
            'fiscal_year_id' => $this->fy->id, 'vendor_account_id' => $this->acc['vendor']->id, 'warehouse_id' => $this->wh->id,
            'invoice_date' => '2026-02-01',
            'lines' => [['item_id' => $this->item->id, 'quantity' => 10, 'unit_price' => 10, 'tax_rate' => 5]],
        ])->assertStatus(201)->json('data.id');

        // الإجماليات: 100 + ضريبة 5 = 105
        $this->actingAs($user)->getJson("/api/purchase-invoices/{$id}")->assertJsonPath('data.total', '105.000');

        $res = $this->actingAs($user)->postJson("/api/purchase-invoices/{$id}/post")->assertOk();
        $res->assertJsonPath('data.status', 'POSTED');

        // المخزون: 10 وحدات بمتوسط 10
        $this->assertSame(10.0, app(InventoryService::class)->totalOnHand($this->item->id));
        $this->assertSame('10.000', $this->item->fresh()->average_cost);

        // القيد: مدين مخزون 100 + ضريبة 5 / دائن مورد 105
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.journal_entry_id'));
        $this->assertSame('105.000', $entry->total_debit);
        $this->assertSame('105.000', $entry->total_credit);
        $this->assertSame('105.000', $entry->lines->firstWhere('account_id', $this->acc['vendor']->id)->credit);
    }

    public function test_sales_invoice_posts_cogs_and_balanced_entry(): void
    {
        $user = $this->env();
        // مخزون ابتدائي عبر استلام مباشر: 10 بسعر 10
        app(InventoryService::class)->receive($this->item, $this->wh->id, 10, 10, '2026-01-01');

        $id = $this->actingAs($user)->postJson('/api/sales-invoices', [
            'fiscal_year_id' => $this->fy->id, 'customer_account_id' => $this->acc['customer']->id, 'warehouse_id' => $this->wh->id,
            'invoice_date' => '2026-02-05',
            'lines' => [['item_id' => $this->item->id, 'quantity' => 4, 'unit_price' => 20, 'tax_rate' => 5]],
        ])->assertStatus(201)->json('data.id');

        $res = $this->actingAs($user)->postJson("/api/sales-invoices/{$id}/post")->assertOk();
        $res->assertJsonPath('data.status', 'POSTED');

        // المخزون نقص لـ 6
        $this->assertSame(6.0, app(InventoryService::class)->totalOnHand($this->item->id));

        // القيد: مدين عميل 84 + تكلفة 40 = دائن إيراد 80 + ضريبة 4 + مخزون 40 = 124
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.journal_entry_id'));
        $this->assertSame('124.000', $entry->total_debit);
        $this->assertSame('124.000', $entry->total_credit);
        $this->assertSame('84.000', $entry->lines->firstWhere('account_id', $this->acc['customer']->id)->debit);
        $this->assertSame('40.000', $entry->lines->firstWhere('account_id', $this->acc['cogs']->id)->debit);
        $this->assertSame('80.000', $entry->lines->firstWhere('account_id', $this->acc['revenue']->id)->credit);
    }

    public function test_cannot_sell_more_than_stock(): void
    {
        $user = $this->env();
        app(InventoryService::class)->receive($this->item, $this->wh->id, 3, 10, '2026-01-01');
        $id = $this->actingAs($user)->postJson('/api/sales-invoices', [
            'fiscal_year_id' => $this->fy->id, 'customer_account_id' => $this->acc['customer']->id, 'warehouse_id' => $this->wh->id,
            'invoice_date' => '2026-02-05',
            'lines' => [['item_id' => $this->item->id, 'quantity' => 10, 'unit_price' => 20]],
        ])->json('data.id');
        $this->actingAs($user)->postJson("/api/sales-invoices/{$id}/post")->assertStatus(422);
    }

    public function test_vat_report_nets_output_minus_input(): void
    {
        $user = $this->env();
        // شراء: ضريبة مدخلات 5 (100 × 5%)
        $pid = $this->actingAs($user)->postJson('/api/purchase-invoices', [
            'fiscal_year_id' => $this->fy->id, 'vendor_account_id' => $this->acc['vendor']->id, 'warehouse_id' => $this->wh->id,
            'invoice_date' => '2026-02-01', 'lines' => [['item_id' => $this->item->id, 'quantity' => 10, 'unit_price' => 10, 'tax_rate' => 5]],
        ])->json('data.id');
        $this->actingAs($user)->postJson("/api/purchase-invoices/{$pid}/post");
        // بيع: ضريبة مخرجات 4 (80 × 5%)
        $sid = $this->actingAs($user)->postJson('/api/sales-invoices', [
            'fiscal_year_id' => $this->fy->id, 'customer_account_id' => $this->acc['customer']->id, 'warehouse_id' => $this->wh->id,
            'invoice_date' => '2026-02-05', 'lines' => [['item_id' => $this->item->id, 'quantity' => 4, 'unit_price' => 20, 'tax_rate' => 5]],
        ])->json('data.id');
        $this->actingAs($user)->postJson("/api/sales-invoices/{$sid}/post");

        $this->actingAs($user)->getJson('/api/reports/vat')->assertOk()
            ->assertJsonPath('data.output_tax', 4)
            ->assertJsonPath('data.input_tax', 5)
            ->assertJsonPath('data.net_vat', -1);
    }

    public function test_cannot_post_twice(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/purchase-invoices', [
            'fiscal_year_id' => $this->fy->id, 'vendor_account_id' => $this->acc['vendor']->id, 'warehouse_id' => $this->wh->id,
            'invoice_date' => '2026-02-01', 'lines' => [['item_id' => $this->item->id, 'quantity' => 1, 'unit_price' => 10]],
        ])->json('data.id');
        $this->actingAs($user)->postJson("/api/purchase-invoices/{$id}/post")->assertOk();
        $this->actingAs($user)->postJson("/api/purchase-invoices/{$id}/post")->assertStatus(422);
    }
}
