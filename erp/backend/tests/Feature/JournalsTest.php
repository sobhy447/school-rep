<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private array $cc = [];
    private FiscalYear $fy;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /** بيئة كاملة: مستخدم بصلاحيات + سنة + حسابات + مراكز تكلفة. */
    private function env(array $extraPerms = []): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'شركة', 'currency_code' => 'KWD']);
        $perms = array_merge([
            'journals.view', 'journals.create', 'journals.edit', 'journals.delete',
            'journals.approve', 'journals.post', 'journals.unpost',
            'vouchers.view', 'vouchers.create', 'vouchers.unpost', 'accounts.view',
        ], $extraPerms);
        $role = Role::create(['company_id' => $this->company->id, 'slug' => 'r', 'name' => 'دور']);
        $ids = collect($perms)->map(fn ($k) => Permission::firstOrCreate(
            ['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k]
        )->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create([
            'company_id' => $this->company->id, 'role_id' => $role->id, 'name' => 'م',
            'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $cid = $this->company->id;
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);

        $mk = fn ($code, $name, $type, $opts = []) => Account::create(array_merge(
            ['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $opts));

        $this->acc['cash'] = $mk('1101', 'الصندوق', 'ASSET', ['is_cash_or_bank' => true]);
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET');
        $this->acc['expense'] = $mk('51', 'مصروفات', 'EXPENSE');
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        $this->acc['parent'] = $mk('2', 'الخصوم', 'LIABILITY', ['accepts_entries' => false]);
        $mk('21', 'موردون', 'LIABILITY', ['parent_id' => $this->acc['parent']->id]);

        $this->cc['main'] = CostCenter::create(['company_id' => $cid, 'code' => 'CC1', 'name' => 'إدارة']);
        $this->cc['extra'] = CostCenter::create(['company_id' => $cid, 'code' => 'EX1', 'name' => 'قضية',
            'linked_account_id' => $this->acc['customer']->id, 'counterparty_name' => 'خصم تجريبي']);

        return $user;
    }

    private function header(array $override = []): array
    {
        return array_merge([
            'fiscal_year_id' => $this->fy->id,
            'entry_date' => '2026-03-01',
            'description' => 'قيد تجريبي',
        ], $override);
    }

    public function test_create_balanced_entry_generates_number(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'credit' => 0, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'debit' => 0, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(201);

        $res->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total_debit', '100.000')
            ->assertJsonPath('data.total_credit', '100.000');
        $this->assertMatchesRegularExpression('/JV-2026-\d{4}/', $res->json('data.entry_number'));
    }

    public function test_unbalanced_entry_rejected(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 90, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    public function test_line_with_both_debit_and_credit_rejected(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    public function test_cannot_post_to_parent_account(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['parent']->id, 'debit' => 50, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 50, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    public function test_primary_cost_center_required_on_line(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 50],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 50, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    public function test_extra_cost_center_resolves_account_and_counterparty(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                // بدون account_id — يُستدعى من مركز التكلفة الإضافي
                ['debit' => 70, 'cost_center_id' => $this->cc['main']->id, 'cost_center_extra_id' => $this->cc['extra']->id],
                ['account_id' => $this->acc['cash']->id, 'credit' => 70, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(201);

        $entry = JournalEntry::withoutGlobalScopes()->with('lines')->find($res->json('data.id'));
        $line = $entry->lines->firstWhere('cost_center_extra_id', $this->cc['extra']->id);
        $this->assertSame($this->acc['customer']->id, $line->account_id);
        $this->assertSame('خصم تجريبي', $line->counterparty_name);
    }

    public function test_lifecycle_approve_then_post(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->json('data.id');

        // لا يمكن الترحيل قبل الاعتماد
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/post")->assertStatus(422);
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');
    }

    public function test_cannot_edit_or_delete_after_post(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->json('data.id');
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/approve");
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/post");

        $this->actingAs($user)->deleteJson("/api/journal-entries/{$id}")->assertStatus(422);
        $this->actingAs($user)->putJson("/api/journal-entries/{$id}", $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 5, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 5, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    public function test_reverse_posted_entry_creates_mirror(): void
    {
        $user = $this->env();
        $id = $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->json('data.id');
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/approve");
        $this->actingAs($user)->postJson("/api/journal-entries/{$id}/post");

        $rev = $this->actingAs($user)->postJson("/api/journal-entries/{$id}/reverse")->assertOk();
        $rev->assertJsonPath('data.status', 'POSTED')
            ->assertJsonPath('data.reversed_entry_id', $id)
            ->assertJsonPath('data.total_debit', '100.000');
        // السطر النقدي صار دائناً في العكس
        $revLines = collect($rev->json('data.lines'));
        $cashLine = $revLines->firstWhere('account_id', $this->acc['cash']->id);
        $this->assertSame('100.000', $cashLine['credit']);
    }

    public function test_date_outside_fiscal_year_rejected(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'entry_date' => '2027-01-01',
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 10, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 10, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    public function test_cannot_enter_in_locked_year(): void
    {
        $user = $this->env();
        $this->fy->update(['status' => 'CLOSED', 'is_locked' => true]);
        $this->actingAs($user)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 10, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 10, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(422);
    }

    // ───────── السندات ─────────

    public function test_payment_voucher_creates_posted_balanced_entry(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/vouchers', [
            'type' => 'PAYMENT',
            'fiscal_year_id' => $this->fy->id,
            'entry_date' => '2026-04-01',
            'party_name' => 'المورد أحمد',
            'main' => ['account_id' => $this->acc['cash']->id, 'amount' => 300],
            'lines' => [
                ['account_id' => $this->acc['expense']->id, 'debit' => 300, 'cost_center_id' => $this->cc['main']->id],
            ],
        ])->assertStatus(201);

        $res->assertJsonPath('data.type', 'PAYMENT')
            ->assertJsonPath('data.status', 'POSTED')
            ->assertJsonPath('data.total_debit', '300.000')
            ->assertJsonPath('data.total_credit', '300.000');
        $this->assertMatchesRegularExpression('/P-2026-\d{4}/', $res->json('data.entry_number'));
        // الجانب الرئيسي دائن (نقدية)
        $main = collect($res->json('data.lines'))->firstWhere('is_main', true);
        $this->assertSame('300.000', $main['credit']);
    }

    public function test_receipt_voucher_main_side_is_debit(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->postJson('/api/vouchers', [
            'type' => 'RECEIPT',
            'fiscal_year_id' => $this->fy->id,
            'entry_date' => '2026-04-02',
            'party_name' => 'العميل خالد',
            'main' => ['account_id' => $this->acc['cash']->id, 'amount' => 500],
            'lines' => [
                ['account_id' => $this->acc['customer']->id, 'credit' => 500, 'cost_center_id' => $this->cc['main']->id],
            ],
        ])->assertStatus(201);

        $res->assertJsonPath('data.type', 'RECEIPT')->assertJsonPath('data.status', 'POSTED');
        $main = collect($res->json('data.lines'))->firstWhere('is_main', true);
        $this->assertSame('500.000', $main['debit']);
    }

    public function test_voucher_main_must_be_cash_or_bank(): void
    {
        $user = $this->env();
        $this->actingAs($user)->postJson('/api/vouchers', [
            'type' => 'PAYMENT',
            'fiscal_year_id' => $this->fy->id,
            'entry_date' => '2026-04-01',
            'main' => ['account_id' => $this->acc['expense']->id, 'amount' => 300], // ليس نقدية/بنك
            'lines' => [
                ['account_id' => $this->acc['customer']->id, 'debit' => 300, 'cost_center_id' => $this->cc['main']->id],
            ],
        ])->assertStatus(422);
    }

    public function test_account_search_returns_leaf_only(): void
    {
        $user = $this->env();
        $res = $this->actingAs($user)->getJson('/api/accounts/search?q=1')->assertOk();
        $codes = collect($res->json('data'))->pluck('code')->all();
        $this->assertContains('1101', $codes);
        $this->assertNotContains('2', $codes); // حساب أب لا يظهر
    }

    public function test_tenant_isolation_on_journals(): void
    {
        $userA = $this->env();
        $this->actingAs($userA)->postJson('/api/journal-entries', $this->header([
            'lines' => [
                ['account_id' => $this->acc['cash']->id, 'debit' => 100, 'cost_center_id' => $this->cc['main']->id],
                ['account_id' => $this->acc['revenue']->id, 'credit' => 100, 'cost_center_id' => $this->cc['main']->id],
            ],
        ]))->assertStatus(201);

        $userB = $this->env(); // شركة أخرى
        $this->actingAs($userB)->getJson('/api/journal-entries')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_permission_enforced_on_create(): void
    {
        // مستخدم بدون journals.create
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $this->company->id, 'slug' => 'r', 'name' => 'د']);
        $role->permissions()->sync([Permission::firstOrCreate(['key' => 'journals.view'],
            ['module' => 'journals', 'label_ar' => 'v', 'label_en' => 'v'])->id]);
        $user = User::create(['company_id' => $this->company->id, 'role_id' => $role->id, 'name' => 'م',
            'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/journal-entries', ['fiscal_year_id' => 1, 'entry_date' => '2026-01-01', 'lines' => []])
            ->assertStatus(403);
    }
}
