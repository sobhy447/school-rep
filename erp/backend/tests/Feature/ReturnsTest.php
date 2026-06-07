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

class ReturnsTest extends TestCase
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
        foreach (['returns', 'accounts'] as $m) foreach (['view', 'create'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($c, $nm, $t, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $c, 'name' => $nm, 'type' => $t], $o));
        $this->acc['inv'] = $mk('1105', 'المخزون', 'ASSET');
        $this->acc['vatin'] = $mk('1106', 'ضريبة المدخلات', 'ASSET');
        $this->acc['vatout'] = $mk('22', 'ضريبة المخرجات', 'LIABILITY');
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);
        $this->acc['vendor'] = $mk('21', 'الموردون', 'LIABILITY', ['party_type' => 'VENDOR']);
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['cogs'] = $mk('53', 'تكلفة', 'EXPENSE');
        CompanySetting::put($cid, 'vat_output_account_id', (string) $this->acc['vatout']->id);
        CompanySetting::put($cid, 'vat_input_account_id', (string) $this->acc['vatin']->id);
        $this->wh = Warehouse::create(['company_id' => $cid, 'code' => 'W1', 'name' => 'مخزن']);
        $this->item = InventoryItem::create(['company_id' => $cid, 'code' => 'IT1', 'name' => 'صنف',
            'inventory_account_id' => $this->acc['inv']->id, 'cogs_account_id' => $this->acc['cogs']->id, 'revenue_account_id' => $this->acc['revenue']->id]);
        app(InventoryService::class)->receive($this->item, $this->wh->id, 10, 10, '2026-01-01'); // متوسط 10
        return $user;
    }

    public function test_sales_return_increases_stock_and_balanced_entry(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/returns', [
            'type' => 'SALES', 'fiscal_year_id' => $this->fy->id, 'party_account_id' => $this->acc['customer']->id,
            'warehouse_id' => $this->wh->id, 'return_date' => '2026-02-01',
            'lines' => [['item_id' => $this->item->id, 'quantity' => 2, 'unit_price' => 20, 'tax_rate' => 5]],
        ])->assertStatus(201);

        // المخزون زاد لـ 12
        $this->assertSame(12.0, app(InventoryService::class)->totalOnHand($this->item->id));
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.journal_entry_id'));
        // مدين إيراد 40 + ضريبة 2 + مخزون 20 = 62 ، دائن عميل 42 + تكلفة 20 = 62
        $this->assertSame('62.000', $entry->total_debit);
        $this->assertSame('62.000', $entry->total_credit);
        $this->assertSame('42.000', $entry->lines->firstWhere('account_id', $this->acc['customer']->id)->credit);
    }

    public function test_purchase_return_reduces_stock_and_balanced_entry(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/returns', [
            'type' => 'PURCHASE', 'fiscal_year_id' => $this->fy->id, 'party_account_id' => $this->acc['vendor']->id,
            'warehouse_id' => $this->wh->id, 'return_date' => '2026-02-01',
            'lines' => [['item_id' => $this->item->id, 'quantity' => 3, 'unit_price' => 10, 'tax_rate' => 5]],
        ])->assertStatus(201);

        $this->assertSame(7.0, app(InventoryService::class)->totalOnHand($this->item->id));
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.journal_entry_id'));
        // مدين مورد 31.5 = دائن مخزون 30 + ضريبة مدخلات 1.5
        $this->assertSame('31.500', $entry->total_debit);
        $this->assertSame('31.500', $entry->total_credit);
        $this->assertSame('31.500', $entry->lines->firstWhere('account_id', $this->acc['vendor']->id)->debit);
    }

    public function test_sales_return_cannot_exceed_no_constraint_but_purchase_needs_stock(): void
    {
        $user = $this->env();
        // مرتجع مشتريات أكبر من المتاح ➜ يرفض (صرف يتجاوز المخزون)
        $this->actingAs($user)->postJson('/api/returns', [
            'type' => 'PURCHASE', 'fiscal_year_id' => $this->fy->id, 'party_account_id' => $this->acc['vendor']->id,
            'warehouse_id' => $this->wh->id, 'return_date' => '2026-02-01',
            'lines' => [['item_id' => $this->item->id, 'quantity' => 100, 'unit_price' => 10]],
        ])->assertStatus(422);
    }
}
