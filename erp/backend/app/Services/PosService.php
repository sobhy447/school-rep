<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\InventoryItem;
use App\Models\PosSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقطة البيع: بيع نقدي سريع يُرحَّل فوراً ➜ صرف مخزني + قيد نقدي مباشر:
 * مدين الصندوق / دائن المبيعات + ضريبة المخرجات + (مدين تكلفة البضاعة / دائن المخزون).
 */
class PosService
{
    public function __construct(private JournalService $journal, private InventoryService $inventory) {}

    public function checkout(int $companyId, ?int $userId, array $data): PosSale
    {
        [$lines, $subtotal, $tax] = $this->prepareLines($data['lines']);
        $total = round($subtotal + $tax, 3);
        $paid = round((float) ($data['paid'] ?? $total), 3);

        return DB::transaction(function () use ($companyId, $userId, $data, $lines, $subtotal, $tax, $total, $paid) {
            $sale = PosSale::query()->create([
                'company_id' => $companyId,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'warehouse_id' => $data['warehouse_id'],
                'cash_account_id' => $data['cash_account_id'],
                'sale_number' => $data['sale_number'] ?? $this->nextNumber($companyId, $data['sale_date']),
                'sale_date' => $data['sale_date'],
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total,
                'paid' => $paid, 'change_amount' => round(max(0, $paid - $total), 3),
                'created_by' => $userId,
            ]);

            $revenueLines = [];
            $cogsLines = [];
            foreach ($lines as $i => $l) {
                $item = InventoryItem::query()->where('company_id', $companyId)->findOrFail($l['item_id']);
                if (! $item->revenue_account_id || ! $item->cogs_account_id || ! $item->inventory_account_id) {
                    throw ValidationException::withMessages(['lines' => ["الصنف {$item->code} ينقصه حساب (مخزون/إيراد/تكلفة)"]]);
                }
                $mv = $this->inventory->issue($item, $data['warehouse_id'], $l['quantity'], $data['sale_date'],
                    ['reference_type' => 'POS', 'reference_id' => $sale->id, 'created_by' => $userId]);
                $cost = (float) $mv->total_cost;
                $sale->lines()->create($l + ['company_id' => $companyId, 'line_number' => $i + 1, 'cost_amount' => $cost]);

                $revenueLines[] = ['account_id' => $item->revenue_account_id, 'debit' => 0, 'credit' => $l['line_total']];
                if ($cost > 0) {
                    $cogsLines[] = ['account_id' => $item->cogs_account_id, 'debit' => $cost, 'credit' => 0];
                    $cogsLines[] = ['account_id' => $item->inventory_account_id, 'debit' => 0, 'credit' => $cost];
                }
            }

            // القيد النقدي المباشر
            $journalLines = [['account_id' => $data['cash_account_id'], 'debit' => $total, 'credit' => 0, 'is_main' => true]];
            $journalLines = array_merge($journalLines, $revenueLines);
            if ($tax > 0) {
                $vatOut = (int) CompanySetting::get($companyId, 'vat_output_account_id', 0);
                if (! $vatOut) throw ValidationException::withMessages(['tax' => ['حساب ضريبة المخرجات غير معرّف']]);
                $journalLines[] = ['account_id' => $vatOut, 'debit' => 0, 'credit' => $tax];
            }
            $journalLines = array_merge($journalLines, $cogsLines);

            $entry = $this->journal->create($companyId, $userId, [
                'fiscal_year_id' => $data['fiscal_year_id'],
                'entry_date' => $data['sale_date'],
                'description' => 'بيع نقدي POS ' . $sale->sale_number,
                'lines' => $journalLines,
            ], 'RECEIPT', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $sale->update(['journal_entry_id' => $entry->id]);
            return $sale->fresh('lines');
        });
    }

    private function prepareLines(array $rawLines): array
    {
        if (empty($rawLines)) throw ValidationException::withMessages(['lines' => ['لا توجد أصناف']]);
        $lines = []; $subtotal = 0; $tax = 0;
        foreach ($rawLines as $l) {
            $qty = round((float) $l['quantity'], 3); $price = round((float) $l['unit_price'], 3);
            if ($qty <= 0 || $price < 0) throw ValidationException::withMessages(['lines' => ['كمية/سعر غير صالح']]);
            $lineTotal = round($qty * $price, 3);
            $rate = round((float) ($l['tax_rate'] ?? 0), 4);
            $lineTax = round($lineTotal * $rate / 100, 3);
            $subtotal += $lineTotal; $tax += $lineTax;
            $lines[] = ['item_id' => (int) $l['item_id'], 'quantity' => $qty, 'unit_price' => $price, 'tax_rate' => $rate, 'tax_amount' => $lineTax, 'line_total' => $lineTotal];
        }
        return [$lines, round($subtotal, 3), round($tax, 3)];
    }

    private function nextNumber(int $companyId, string $date): string
    {
        $year = substr($date, 0, 4);
        $count = PosSale::query()->withoutGlobalScopes()->where('company_id', $companyId)->whereYear('sale_date', $year)->count();
        return 'POS-' . $year . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
