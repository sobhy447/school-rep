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
use App\Services\PdfService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $acc = [];
    private FiscalYear $fy;

    protected function tearDown(): void { TenantContext::clear(); parent::tearDown(); }

    private function env(): User
    {
        $this->company = Company::create(['code' => 'C' . uniqid(), 'name' => 'شركة الاختبار', 'currency_code' => 'KWD']);
        $cid = $this->company->id;
        $role = Role::create(['company_id' => $cid, 'slug' => 'r', 'name' => 'د']);
        $keys = [];
        foreach (['journals', 'accounts'] as $m) foreach (['view', 'edit'] as $a) $keys[] = "$m.$a";
        $ids = collect($keys)->map(fn ($k) => Permission::firstOrCreate(['key' => $k], ['module' => explode('.', $k)[0], 'label_ar' => $k, 'label_en' => $k])->id)->all();
        $role->permissions()->sync($ids);
        $user = User::create(['company_id' => $cid, 'role_id' => $role->id, 'name' => 'م', 'email' => 'u' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $mk = fn ($c, $nm, $t, $o = []) => Account::create(array_merge(['company_id' => $cid, 'code' => $c, 'name' => $nm, 'type' => $t], $o));
        $this->acc['customer'] = $mk('1103', 'العملاء', 'ASSET', ['party_type' => 'CUSTOMER']);
        $this->acc['revenue'] = $mk('41', 'إيرادات', 'REVENUE');
        return $user;
    }

    private function postedEntry(): JournalEntry
    {
        $cid = $this->company->id;
        $e = JournalEntry::create(['company_id' => $cid, 'fiscal_year_id' => $this->fy->id, 'type' => 'MANUAL',
            'entry_number' => 'JV-001', 'entry_date' => '2026-03-01', 'status' => 'POSTED', 'total_debit' => 300, 'total_credit' => 300]);
        $e->lines()->create(['company_id' => $cid, 'line_number' => 1, 'account_id' => $this->acc['customer']->id, 'debit' => 300, 'credit' => 0, 'description' => 'أتعاب قضية']);
        $e->lines()->create(['company_id' => $cid, 'line_number' => 2, 'account_id' => $this->acc['revenue']->id, 'debit' => 0, 'credit' => 300]);
        return $e->load('lines');
    }

    private function assertPdf($res): void
    {
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_journal_entry_pdf(): void
    {
        $user = $this->env();
        $e = $this->postedEntry();
        $this->assertPdf($this->actingAs($user)->get("/api/journal-entries/{$e->id}/pdf"));
    }

    public function test_account_statement_pdf(): void
    {
        $user = $this->env();
        $this->postedEntry();
        $this->assertPdf($this->actingAs($user)->get("/api/accounts/{$this->acc['customer']->id}/statement/pdf"));
    }

    public function test_statement_pdf_merges_attachment_pages(): void
    {
        $user = $this->env();
        Storage::fake('local');
        $e = $this->postedEntry();
        $line = $e->lines->firstWhere('debit', '300.000');
        $line->update(['page_from' => 1, 'page_to' => 1]);

        // أنشئ ملف PDF صالح فعلاً عبر mPDF واحفظه كمرفق
        $pdf = app(PdfService::class)->render('<h2>مرفق تجريبي</h2><p>صفحة المستند</p>');
        Storage::disk('local')->put('attachments/test.pdf', $pdf);
        DocumentAttachment::create(['company_id' => $this->company->id, 'journal_entry_id' => $e->id,
            'file_name' => 'test.pdf', 'stored_name' => 'test.pdf', 'file_path' => 'attachments/test.pdf', 'file_size' => strlen($pdf), 'total_pages' => 1]);

        $this->assertPdf($this->actingAs($user)->get("/api/accounts/{$this->acc['customer']->id}/statement/pdf?with_attachments=1"));
    }

    public function test_pdf_requires_permission(): void
    {
        $company = Company::create(['code' => 'C' . uniqid(), 'name' => 'ش', 'currency_code' => 'KWD']);
        $role = Role::create(['company_id' => $company->id, 'slug' => 'r', 'name' => 'د']);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'م', 'email' => 'x' . uniqid() . '@t.test', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->actingAs($user)->get("/api/accounts/1/statement/pdf")->assertStatus(403);
    }
}
