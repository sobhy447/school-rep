<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DocumentAttachment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\PdfService;
use App\Support\AccountType;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentPdfController extends Controller
{
    public function __construct(private PdfService $pdf) {}

    private function n($v): string { return number_format((float) $v, 3); }

    private function stream(string $content, string $name)
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $name . '"',
        ]);
    }

    private function company(): string
    {
        $c = Account::query()->getModel(); // فقط للوصول للاتصال
        $name = optional(\App\Models\Company::find(TenantContext::id()))->name ?? '';
        return $name;
    }

    public function salesInvoice(int $id)
    {
        $inv = SalesInvoice::query()->with('lines')->findOrFail($id);
        return $this->stream($this->pdf->render($this->invoiceHtml('فاتورة مبيعات', $inv)), "sales-{$inv->invoice_number}.pdf");
    }

    public function purchaseInvoice(int $id)
    {
        $inv = PurchaseInvoice::query()->with('lines')->findOrFail($id);
        return $this->stream($this->pdf->render($this->invoiceHtml('فاتورة مشتريات', $inv)), "purchase-{$inv->invoice_number}.pdf");
    }

    private function invoiceHtml(string $title, $inv): string
    {
        $rows = '';
        foreach ($inv->lines as $l) {
            $item = \App\Models\InventoryItem::find($l->item_id);
            $rows .= '<tr><td>' . e($item?->name) . '</td><td>' . $this->n($l->quantity) . '</td><td>' . $this->n($l->unit_price)
                . '</td><td>' . $this->n($l->tax_amount) . '</td><td>' . $this->n($l->line_total) . '</td></tr>';
        }
        return '<div class="head"><div><h2>' . $title . '</h2><div class="muted">' . e($this->company()) . '</div></div>'
            . '<div><b>رقم:</b> ' . e($inv->invoice_number) . '<br><b>التاريخ:</b> ' . $inv->invoice_date->toDateString() . '</div></div>'
            . '<table><thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الضريبة</th><th>الإجمالي</th></tr></thead><tbody>' . $rows . '</tbody>'
            . '<tr class="tot"><td colspan="4">الإجمالي قبل الضريبة</td><td>' . $this->n($inv->subtotal) . '</td></tr>'
            . '<tr class="tot"><td colspan="4">الضريبة</td><td>' . $this->n($inv->tax_amount) . '</td></tr>'
            . '<tr class="tot"><td colspan="4">الإجمالي</td><td>' . $this->n($inv->total) . '</td></tr></table>';
    }

    public function journalEntry(int $id)
    {
        $entry = JournalEntry::query()->with('lines.account:id,code,name')->findOrFail($id);
        $rows = '';
        foreach ($entry->lines as $l) {
            $rows .= '<tr><td>' . e($l->account?->code . ' ' . $l->account?->name) . '</td><td>' . e($l->description)
                . '</td><td>' . $this->n($l->debit) . '</td><td>' . $this->n($l->credit) . '</td></tr>';
        }
        $html = '<div class="head"><div><h2>قيد/سند: ' . e($entry->entry_number) . '</h2><div class="muted">' . e($this->company()) . '</div></div>'
            . '<div><b>التاريخ:</b> ' . $entry->entry_date->toDateString() . '<br><b>الحالة:</b> ' . e($entry->status) . '</div></div>'
            . '<p>' . e($entry->description) . '</p>'
            . '<table><thead><tr><th>الحساب</th><th>البيان</th><th>مدين</th><th>دائن</th></tr></thead><tbody>' . $rows . '</tbody>'
            . '<tr class="tot"><td colspan="2">الإجمالي</td><td>' . $this->n($entry->total_debit) . '</td><td>' . $this->n($entry->total_credit) . '</td></tr></table>';
        return $this->stream($this->pdf->render($html), "entry-{$entry->entry_number}.pdf");
    }

    /** كشف حساب — مع خيار دمج صفحات المرفقات المربوطة بكل سطر. */
    public function statement(Request $request, int $id)
    {
        $account = Account::query()->findOrFail($id);
        $withAtt = $request->boolean('with_attachments');

        $lines = JournalLine::query()->where('company_id', TenantContext::id())->where('account_id', $id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'POSTED'))
            ->with('journalEntry:id,entry_number,entry_date,description')
            ->get()->sortBy(fn ($l) => [optional($l->journalEntry)->entry_date, $l->id])->values();

        $running = $account->signedOpeningBalance();
        $rows = '<tr class="tot"><td colspan="4">رصيد افتتاحي</td><td>' . $this->n($running) . '</td></tr>';
        $attachments = [];
        foreach ($lines as $l) {
            $running = round($running + AccountType::signedBalance($account->type, (float) $l->debit, (float) $l->credit), 3);
            $pageNote = $l->page_from ? ('ص ' . $l->page_from . ($l->page_to && $l->page_to != $l->page_from ? '-' . $l->page_to : '')) : '';
            $rows .= '<tr><td>' . $l->journalEntry?->entry_number . '</td><td>' . optional($l->journalEntry?->entry_date)->toDateString()
                . '</td><td>' . $this->n($l->debit) . '</td><td>' . $this->n($l->credit) . '</td><td>' . $this->n($running) . '</td></tr>';

            if ($withAtt && $l->page_from) {
                $att = DocumentAttachment::query()->where('journal_entry_id', $l->journal_entry_id)->first();
                if ($att && Storage::disk('local')->exists($att->file_path)) {
                    $attachments[] = ['path' => Storage::disk('local')->path($att->file_path),
                        'from' => $l->page_from, 'to' => $l->page_to ?: $l->page_from];
                }
            }
        }
        $html = '<div class="head"><div><h2>كشف حساب: ' . e($account->name) . '</h2><div class="muted">' . e($this->company()) . '</div></div></div>'
            . '<table><thead><tr><th>المستند</th><th>التاريخ</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead><tbody>' . $rows
            . '<tr class="tot"><td colspan="4">الرصيد الختامي</td><td>' . $this->n($running) . '</td></tr></tbody></table>';

        $content = $withAtt && ! empty($attachments)
            ? $this->pdf->renderWithAttachments($html, $attachments)
            : $this->pdf->render($html);
        return $this->stream($content, "statement-{$account->code}.pdf");
    }
}
