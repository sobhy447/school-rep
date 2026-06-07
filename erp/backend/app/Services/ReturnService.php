<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\InventoryItem;
use App\Models\TradeReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * المرتجعات (تُرحَّل فوراً):
 * - مبيعات: إرجاع للمخزون + (مدين المبيعات + ضريبة المخرجات / دائن العميل) + (مدين المخزون / دائن التكلفة).
 * - مشتريات: صرف من المخزون + (مدين المورد / دائن المخزون + ضريبة المدخلات).
 */
class ReturnService
{
    public function __construct(private JournalService $journal, private InventoryService $inventory) {}

    public function create(int $companyId, ?int $userId, string $type, array $data): TradeReturn
    {
        [$lines, $subtotal, $tax] = $this->prepareLines($data['lines']);
        $total = round($subtotal + $tax, 3);

        return DB::transaction(function () use ($companyId, $userId, $type, $data, $lines, $subtotal, $tax, $total) {
            $ret = TradeReturn::query()->create([
                'company_id' => $companyId, 'fiscal_year_id' => $data['fiscal_year_id'], 'type' => $type,
                'party_account_id' => $data['party_account_id'], 'warehouse_id' => $data['warehouse_id'],
                'return_number' => $data['return_number'] ?? $this->nextNumber($companyId, $type, $data['return_date']),
                'return_date' => $data['return_date'], 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total,
                'notes' => $data['notes'] ?? null, 'created_by' => $userId,
            ]);

            $journalLines = [];
            if ($type === 'SALES') {
                // الجانب: مدين المبيعات + ضريبة المخرجات / دائن العميل
                $revenueLines = [];
                $cogsLines = [];
                foreach ($lines as $i => $l) {
                    $item = $this->item($companyId, $l['item_id']);
                    // إرجاع للمخزون بتكلفة المتوسط الحالية
                    $mv = $this->inventory->receive($item, $data['warehouse_id'], $l['quantity'], (float) $item->average_cost,
                        $data['return_date'], ['reference_type' => 'SALES_RETURN', 'reference_id' => $ret->id, 'created_by' => $userId]);
                    $cost = (float) $mv->total_cost;
                    $ret->lines()->create($l + ['company_id' => $companyId, 'line_number' => $i + 1, 'cost_amount' => $cost]);
                    $revenueLines[] = ['account_id' => $item->revenue_account_id, 'debit' => $l['line_total'], 'credit' => 0];
                    if ($cost > 0) {
                        $cogsLines[] = ['account_id' => $item->inventory_account_id, 'debit' => $cost, 'credit' => 0];
                        $cogsLines[] = ['account_id' => $item->cogs_account_id, 'debit' => 0, 'credit' => $cost];
                    }
                }
                $journalLines = $revenueLines;
                if ($tax > 0) {
                    $vatOut = $this->setting($companyId, 'vat_output_account_id', 'ضريبة المخرجات');
                    $journalLines[] = ['account_id' => $vatOut, 'debit' => $tax, 'credit' => 0];
                }
                $journalLines[] = ['account_id' => $data['party_account_id'], 'debit' => 0, 'credit' => $total, 'is_main' => true];
                $journalLines = array_merge($journalLines, $cogsLines);
            } else { // PURCHASE
                $journalLines[] = ['account_id' => $data['party_account_id'], 'debit' => $total, 'credit' => 0, 'is_main' => true];
                foreach ($lines as $i => $l) {
                    $item = $this->item($companyId, $l['item_id']);
                    $this->inventory->issue($item, $data['warehouse_id'], $l['quantity'], $data['return_date'],
                        ['reference_type' => 'PURCHASE_RETURN', 'reference_id' => $ret->id, 'created_by' => $userId]);
                    $ret->lines()->create($l + ['company_id' => $companyId, 'line_number' => $i + 1, 'cost_amount' => 0]);
                    $journalLines[] = ['account_id' => $item->inventory_account_id, 'debit' => 0, 'credit' => $l['line_total']];
                }
                if ($tax > 0) {
                    $vatIn = $this->setting($companyId, 'vat_input_account_id', 'ضريبة المدخلات');
                    $journalLines[] = ['account_id' => $vatIn, 'debit' => 0, 'credit' => $tax];
                }
            }

            $entry = $this->journal->create($companyId, $userId, [
                'fiscal_year_id' => $data['fiscal_year_id'], 'entry_date' => $data['return_date'],
                'description' => ($type === 'SALES' ? 'مرتجع مبيعات ' : 'مرتجع مشتريات ') . $ret->return_number,
                'lines' => $journalLines,
            ], $type === 'SALES' ? 'PAYMENT' : 'RECEIPT', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $ret->update(['journal_entry_id' => $entry->id]);
            return $ret->fresh('lines');
        });
    }

    private function item(int $companyId, int $id): InventoryItem
    {
        $item = InventoryItem::query()->where('company_id', $companyId)->findOrFail($id);
        if (! $item->inventory_account_id || ! $item->revenue_account_id || ! $item->cogs_account_id) {
            throw ValidationException::withMessages(['lines' => ["الصنف {$item->code} ينقصه حساب (مخزون/إيراد/تكلفة)"]]);
        }
        return $item;
    }

    private function setting(int $companyId, string $key, string $label): int
    {
        $id = (int) CompanySetting::get($companyId, $key, 0);
        if (! $id) {
            throw ValidationException::withMessages(['tax' => ["حساب {$label} غير معرّف"]]);
        }
        return $id;
    }

    private function prepareLines(array $rawLines): array
    {
        if (empty($rawLines)) throw ValidationException::withMessages(['lines' => ['المرتجع يحتاج سطراً واحداً']]);
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

    private function nextNumber(int $companyId, string $type, string $date): string
    {
        $year = substr($date, 0, 4);
        $prefix = $type === 'SALES' ? 'SR' : 'PR';
        $count = TradeReturn::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('type', $type)->whereYear('return_date', $year)->count();
        return "{$prefix}-{$year}-" . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
