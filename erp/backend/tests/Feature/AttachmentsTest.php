<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\DocumentAttachment;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private FiscalYear $fy;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function env(): User
    {
        Storage::fake('local');
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $keys = [];
        foreach (['journals', 'accounts'] as $m) foreach (['view', 'edit', 'create', 'post'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($code, $name, $type, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type], $o));
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        return $user;
    }

    private function postedEntry(): JournalEntry
    {
        $cid = $this->company->id;
        $entry = JournalEntry::create(['company_id' => $cid, 'fiscal_year_id' => $this->fy->id, 'type' => 'MANUAL',
            'entry_number' => 'JV-' . uniqid(), 'entry_date' => '2026-03-01', 'status' => 'POSTED', 'total_debit' => 300, 'total_credit' => 300]);
        $entry->lines()->create(['company_id' => $cid, 'line_number' => 1, 'account_id' => $this->acc['customer']->id, 'debit' => 300, 'credit' => 0, 'description' => 'أتعاب']);
        $entry->lines()->create(['company_id' => $cid, 'line_number' => 2, 'account_id' => $this->acc['revenue']->id, 'debit' => 0, 'credit' => 300]);
        return $entry->load('lines');
    }

    public function test_upload_pdf_and_link_pages_then_statement(): void
    {
        $user = $this->env();
        $entry = $this->postedEntry();
        $line = $entry->lines->firstWhere('debit', '300.000');

        // رفع ملف PDF
        $file = UploadedFile::fake()->create('voucher.pdf', 120, 'application/pdf');
        $att = $this->actingAs($user)->postJson("/api/journal-entries/{$entry->id}/attachments", ['file' => $file, 'total_pages' => 5])
            ->assertStatus(201)->json('data');
        $this->assertDatabaseHas('document_attachments', ['id' => $att['id'], 'journal_entry_id' => $entry->id]);

        // ربط السطر بالصفحات من 2 إلى 4
        $this->actingAs($user)->postJson("/api/journal-entries/{$entry->id}/line-pages", [
            'lines' => [['line_id' => $line->id, 'page_from' => 2, 'page_to' => 4]],
        ])->assertOk();
        $this->assertDatabaseHas('journal_lines', ['id' => $line->id, 'page_from' => 2, 'page_to' => 4]);

        // كشف الحساب يعرض مرجع المرفق والصفحات
        $st = $this->actingAs($user)->getJson("/api/accounts/{$this->acc['customer']->id}/statement")->assertOk();
        $st->assertJsonPath('data.lines.0.page_from', 2)
           ->assertJsonPath('data.lines.0.page_to', 4)
           ->assertJsonPath('data.lines.0.attachment_id', $att['id']);
    }

    public function test_single_page_sets_to_equals_from(): void
    {
        $user = $this->env();
        $entry = $this->postedEntry();
        $line = $entry->lines->first();
        $this->actingAs($user)->postJson("/api/journal-entries/{$entry->id}/line-pages", [
            'lines' => [['line_id' => $line->id, 'page_from' => 3]],
        ])->assertOk();
        $this->assertDatabaseHas('journal_lines', ['id' => $line->id, 'page_from' => 3, 'page_to' => 3]);
    }

    public function test_non_pdf_rejected(): void
    {
        $user = $this->env();
        $entry = $this->postedEntry();
        $file = UploadedFile::fake()->create('x.txt', 10, 'text/plain');
        $this->actingAs($user)->postJson("/api/journal-entries/{$entry->id}/attachments", ['file' => $file])->assertStatus(422);
    }

    public function test_download_and_delete(): void
    {
        $user = $this->env();
        $entry = $this->postedEntry();
        $file = UploadedFile::fake()->create('v.pdf', 50, 'application/pdf');
        $id = $this->actingAs($user)->postJson("/api/journal-entries/{$entry->id}/attachments", ['file' => $file])->json('data.id');

        $this->actingAs($user)->get("/api/attachments/{$id}/download")->assertOk();
        $this->actingAs($user)->deleteJson("/api/attachments/{$id}")->assertOk();
        $this->assertDatabaseMissing('document_attachments', ['id' => $id]);
    }

    public function test_tenant_isolation(): void
    {
        $userA = $this->env();
        $entry = $this->postedEntry();
        // مستخدم شركة أخرى لا يصل لقيد الشركة A
        $companyB = Company::create(['code' => 'CB' . uniqid(), 'name' => 'ب', 'currency_code' => 'KWD']);
        $roleB = Role::create(['company_id' => $companyB->id, 'slug' => 'r', 'name' => 'د']);
        $roleB->permissions()->sync(Permission::whereIn('key', ['journals.view', 'journals.edit'])->pluck('id'));
        $userB = User::create(['company_id' => $companyB->id, 'role_id' => $roleB->id, 'name' => 'ب', 'email' => 'b' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $file = UploadedFile::fake()->create('v.pdf', 20, 'application/pdf');
        $this->actingAs($userB)->postJson("/api/journal-entries/{$entry->id}/attachments", ['file' => $file])->assertStatus(404);
    }
}
