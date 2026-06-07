<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\InventoryItem;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * فاتورة المبيعات: عند الترحيل: صرف مخزني (بتكلفة المتوسط) + قيد تلقائي:
 * مدين العميل / دائن المبيعات / دائن ضريبة المخرجات + مدين تكلفة البضاعة / دائن المخزون.
 */
class SaleService
{
    public function __construct(private JournalService $journal, private InventoryService $inventory) {}

    public function create(int $companyId, ?int $userId, array $data): SalesInvoice
    {
        [$lines, $subtotal, $tax] = $this->prepareLines($data['lines']);

        return DB::transaction(function () use ($companyId, $userId, $data, $lines, $subtotal, $tax) {
            $invoice = SalesInvoice::query()->create([
                'company_id' => $companyId,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'customer_account_id' => $data['customer_account_id'],
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

    public function post(SalesInvoice $invoice, ?int $userId): SalesInvoice
    {
        if ($invoice->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => ['الفاتورة مُرحَّلة بالفعل']]);
        }
        $invoice->load('lines');

        return DB::transaction(function () use ($invoice, $userId) {
            $revenueLines = [];
            $cogsLines = [];
            $totalCost = 0;
            foreach ($invoice->lines as $line) {
                $item = InventoryItem::query()->findOrFail($line->item_id);
                if (! $item->revenue_account_id || ! $item->cogs_account_id || ! $item->inventory_account_id) {
                    throw ValidationException::withMessages(['lines' => ["الصنف {$item->code} ينقصه حساب (مخزون/إيراد/تكلفة)"]]);
                }
                // صرف مخزني بتكلفة المتوسط ➜ تكلفة البضاعة المباعة
                $mv = $this->inventory->issue($item, $invoice->warehouse_id, (float) $line->quantity,
                    $invoice->invoice_date->toDateString(), ['reference_type' => 'SALE', 'reference_id' => $invoice->id, 'created_by' => $userId]);
                $cost = (float) $mv->total_cost;
                $line->update(['cost_amount' => $cost]);
                $totalCost += $cost;

                $revenueLines[] = ['account_id' => $item->revenue_account_id, 'debit' => 0, 'credit' => (float) $line->line_total];
                if ($cost > 0) {
                    $cogsLines[] = ['account_id' => $item->cogs_account_id, 'debit' => $cost, 'credit' => 0];
                    $cogsLines[] = ['account_id' => $item->inventory_account_id, 'debit' => 0, 'credit' => $cost];
                }
            }

            $journalLines = [['account_id' => $invoice->customer_account_id, 'debit' => (float) $invoice->total, 'credit' => 0, 'is_main' => true]];
            $journalLines = array_merge($journalLines, $revenueLines);
            if ((float) $invoice->tax_amount > 0) {
                $vatOut = (int) CompanySetting::get($invoice->company_id, 'vat_output_account_id', 0);
                if (! $vatOut) throw ValidationException::withMessages(['tax' => ['حساب ضريبة المخرجات غير معرّف']]);
                $journalLines[] = ['account_id' => $vatOut, 'debit' => 0, 'credit' => (float) $invoice->tax_amount];
            }
            $journalLines = array_merge($journalLines, $cogsLines);

            $entry = $this->journal->create($invoice->company_id, $userId, [
                'fiscal_year_id' => $invoice->fiscal_year_id,
                'entry_date' => $invoice->invoice_date->toDateString(),
                'description' => 'فاتورة مبيعات ' . $invoice->invoice_number,
                'lines' => $journalLines,
            ], 'RECEIPT', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $invoice->update(['status' => 'POSTED', 'journal_entry_id' => $entry->id, 'posted_by' => $userId, 'posted_at' => now()]);
            return $invoice->fresh('lines');
        });
    }

    private function prepareLines(array $rawLines): array
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
        $count = SalesInvoice::query()->withoutGlobalScopes()->where('company_id', $companyId)->whereYear('invoice_date', $year)->count();
        return 'SI-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
