<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\InventoryItem;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * فاتورة المشتريات: إدخال أصناف بتكلفة، ثم عند الترحيل: استلام مخزني + قيد تلقائي
 * (مدين المخزون + مدين ضريبة المدخلات / دائن المورد).
 */
class PurchaseService
{
    public function __construct(private JournalService $journal, private InventoryService $inventory) {}

    public function create(int $companyId, ?int $userId, array $data): PurchaseInvoice
    {
        [$lines, $subtotal, $tax] = $this->prepareLines($companyId, $data['lines']);

        return DB::transaction(function () use ($companyId, $userId, $data, $lines, $subtotal, $tax) {
            $invoice = PurchaseInvoice::query()->create([
                'company_id' => $companyId,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'vendor_account_id' => $data['vendor_account_id'],
                'warehouse_id' => $data['warehouse_id'],
                'invoice_number' => $data['invoice_number'] ?? $this->nextNumber($companyId, $data['invoice_date']),
                'invoice_date' => $data['invoice_date'],
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => round($subtotal + $tax, 3),
                'status' => 'DRAFT', 'notes' => $data['notes'] ?? null, 'created_by' => $userId,
            ]);
            foreach ($lines as $i => $l) {
                $invoice->lines()->create($l + ['company_id' => $companyId, 'line_number' => $i + 1]);
            }
            return $invoice->load('lines');
        });
    }

    /** ترحيل الفاتورة: استلام مخزني لكل سطر + إنشاء القيد المحاسبي. */
    public function post(PurchaseInvoice $invoice, ?int $userId): PurchaseInvoice
    {
        if ($invoice->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => ['الفاتورة مُرحَّلة بالفعل']]);
        }
        $invoice->load('lines');

        return DB::transaction(function () use ($invoice, $userId) {
            $journalLines = [];
            foreach ($invoice->lines as $line) {
                $item = InventoryItem::query()->findOrFail($line->item_id);
                if (! $item->inventory_account_id) {
                    throw ValidationException::withMessages(['lines' => ["الصنف {$item->code} بلا حساب مخزون"]]);
                }
                // استلام مخزني (يحدّث متوسط التكلفة)
                $this->inventory->receive($item, $invoice->warehouse_id, (float) $line->quantity, (float) $line->unit_price,
                    $invoice->invoice_date->toDateString(), ['reference_type' => 'PURCHASE', 'reference_id' => $invoice->id, 'created_by' => $userId]);
                $journalLines[] = ['account_id' => $item->inventory_account_id, 'debit' => (float) $line->line_total, 'credit' => 0];
            }
            if ((float) $invoice->tax_amount > 0) {
                $vatIn = (int) CompanySetting::get($invoice->company_id, 'vat_input_account_id', 0);
                if (! $vatIn) throw ValidationException::withMessages(['tax' => ['حساب ضريبة المدخلات غير معرّف']]);
                $journalLines[] = ['account_id' => $vatIn, 'debit' => (float) $invoice->tax_amount, 'credit' => 0];
            }
            $journalLines[] = ['account_id' => $invoice->vendor_account_id, 'debit' => 0, 'credit' => (float) $invoice->total, 'is_main' => true];

            $entry = $this->journal->create($invoice->company_id, $userId, [
                'fiscal_year_id' => $invoice->fiscal_year_id,
                'entry_date' => $invoice->invoice_date->toDateString(),
                'description' => 'فاتورة مشتريات ' . $invoice->invoice_number,
                'lines' => $journalLines,
            ], 'PAYMENT', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $invoice->update(['status' => 'POSTED', 'journal_entry_id' => $entry->id, 'posted_by' => $userId, 'posted_at' => now()]);
            return $invoice->fresh('lines');
        });
    }

    private function prepareLines(int $companyId, array $rawLines): array
    {
        if (empty($rawLines)) throw ValidationException::withMessages(['lines' => ['الفاتورة تحتاج سطراً واحداً']]);
        $lines = []; $subtotal = 0; $tax = 0;
        foreach ($rawLines as $l) {
            $qty = round((float) $l['quantity'], 3); $price = round((float) $l['unit_price'], 3);
            if ($qty <= 0 || $price < 0) throw ValidationException::withMessages(['lines' => ['كمية/سعر غير صالح']]);
            $lineTotal = round($qty * $price, 3);
            $rate = round((float) ($l['tax_rate'] ?? 0), 4);
            $lineTax = round($lineTotal * $rate / 100, 3);
            $subtotal += $lineTotal; $tax += $lineTax;
            $lines[] = ['item_id' => $l['item_id'], 'quantity' => $qty, 'unit_price' => $price,
                'tax_rate' => $rate, 'tax_amount' => $lineTax, 'line_total' => $lineTotal, 'cost_amount' => 0];
        }
        return [$lines, round($subtotal, 3), round($tax, 3)];
    }

    private function nextNumber(int $companyId, string $date): string
    {
        $year = substr($date, 0, 4);
        $count = PurchaseInvoice::query()->withoutGlobalScopes()->where('company_id', $companyId)->whereYear('invoice_date', $year)->count();
        return 'PI-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
