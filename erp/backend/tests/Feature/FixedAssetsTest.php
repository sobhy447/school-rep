<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetsTest extends TestCase
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
        foreach (['fixed_assets', 'journals', 'accounts'] as $m) foreach (['view', 'create', 'edit', 'delete', 'post'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($code, $name, $type, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $o));
        $this->acc['cash'] = $mk('1101', 'الصندوق', 'ASSET', ['is_cash_or_bank' => true]);
        $this->acc['asset'] = $mk('1201', 'سيارات', 'ASSET');
        $this->acc['accdep'] = $mk('1202', 'مجمع إهلاك', 'ASSET');
        $this->acc['depexp'] = $mk('52', 'مصروف إهلاك', 'EXPENSE');
        $this->acc['gainloss'] = $mk('42', 'أرباح بيع أصول', 'REVENUE');
        return $user;
    }

    private function createAsset(User $user, array $over = []): int
    {
        return $this->actingAs($user)->postJson('/api/fixed-assets', array_merge([
            'code' => 'A' . uniqid(), 'name' => 'سيارة',
            'asset_account_id' => $this->acc['asset']->id,
            'accumulated_depreciation_account_id' => $this->acc['accdep']->id,
            'depreciation_expense_account_id' => $this->acc['depexp']->id,
            'acquisition_date' => '2026-01-01', 'cost' => 1200, 'salvage_value' => 0,
            'useful_life_months' => 12, 'method' => 'STRAIGHT_LINE',
        ], $over))->assertStatus(201)->json('data.id');
    }

    public function test_create_asset(): void
    {
        $user = $this->env();
        $id = $this->createAsset($user);
        $this->assertNotNull($id);
    }

    public function test_straight_line_schedule(): void
    {
        $user = $this->env();
        $id = $this->createAsset($user);
        $this->actingAs($user)->getJson("/api/fixed-assets/{$id}/schedule")->assertOk()
            ->assertJsonPath('data.monthly', 100)        // 1200 / 12
            ->assertJsonPath('data.rows.0.depreciation', 100)
            ->assertJsonPath('data.rows.0.book_value', 1100);
    }

    public function test_declining_balance_amount(): void
    {
        $user = $this->env();
        $id = $this->createAsset($user, ['method' => 'DECLINING_BALANCE', 'declining_rate' => 24]); // 24%/سنة
        // 1200 * 0.24 / 12 = 24
        $this->actingAs($user)->getJson("/api/fixed-assets/{$id}/schedule")->assertOk()
            ->assertJsonPath('data.monthly', 24);
    }

    public function test_depreciate_creates_posted_entry(): void
    {
        $user = $this->env();
        $id = $this->createAsset($user);
        $res = $this->actingAs($user)->postJson("/api/fixed-assets/{$id}/depreciate", [
            'period_date' => '2026-01-31', 'fiscal_year_id' => $this->fy->id,
        ])->assertOk();
        $res->assertJsonPath('data.amount', '100.000');

        $entryId = $res->json('data.journal_entry_id');
        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($entryId);
        $this->assertSame('POSTED', $entry->status);
        $this->assertSame('100.000', $entry->lines->firstWhere('account_id', $this->acc['depexp']->id)->debit);
        $this->assertSame('100.000', $entry->lines->firstWhere('account_id', $this->acc['accdep']->id)->credit);

        // الأصل: مجمع الإهلاك 100، القيمة الدفترية 1100
        $this->actingAs($user)->getJson("/api/fixed-assets/{$id}")->assertOk()
            ->assertJsonPath('data.accumulated_depreciation', '100.000')
            ->assertJsonPath('data.book_value', 1100);
    }

    public function test_cannot_depreciate_same_period_twice(): void
    {
        $user = $this->env();
        $id = $this->createAsset($user);
        $p = ['period_date' => '2026-01-31', 'fiscal_year_id' => $this->fy->id];
        $this->actingAs($user)->postJson("/api/fixed-assets/{$id}/depreciate", $p)->assertOk();
        $this->actingAs($user)->postJson("/api/fixed-assets/{$id}/depreciate", $p)->assertStatus(422);
    }

    public function test_dispose_with_gain(): void
    {
        $user = $this->env();
        $id = $this->createAsset($user);
        // إهلاك شهر واحد ← مجمع 100، دفترية 1100
        $this->actingAs($user)->postJson("/api/fixed-assets/{$id}/depreciate", ['period_date' => '2026-01-31', 'fiscal_year_id' => $this->fy->id]);

        $res = $this->actingAs($user)->postJson("/api/fixed-assets/{$id}/dispose", [
            'disposal_date' => '2026-02-15', 'proceeds' => 1150, 'fiscal_year_id' => $this->fy->id,
            'cash_account_id' => $this->acc['cash']->id, 'gain_loss_account_id' => $this->acc['gainloss']->id,
        ])->assertOk();
        $res->assertJsonPath('data.status', 'DISPOSED');

        // قيد الاستبعاد متوازن: مدين نقدية 1150 + مجمع 100 = دائن أصل 1200 + ربح 50
        $entry = JournalEntry::withoutGlobalScopes()->where('company_id', $this->company->id)
            ->where('description', 'like', 'استبعاد%')->with('lines')->latest('id')->first();
        $this->assertSame('1250.000', $entry->total_debit);
        $this->assertSame('1250.000', $entry->total_credit);
        $this->assertSame('50.000', $entry->lines->firstWhere('account_id', $this->acc['gainloss']->id)->credit);
    }

    public function test_permission_enforced(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $role->permissions()->sync([Permission::firstOrCreate(['key' => 'fixed_assets.view'], ['module' => 'fixed_assets', 'label_ar' => 'v', 'label_en' => 'v'])->id]);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->postJson('/api/fixed-assets', ['code' => 'X'])->assertStatus(403);
    }
}
