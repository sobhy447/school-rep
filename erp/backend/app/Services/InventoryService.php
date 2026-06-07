<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق المخزون: متوسط التكلفة المرجّح + الأرصدة + حركات (استلام/صرف/تحويل/تسوية).
 */
class InventoryService
{
    private const SIGN = ['IN' => 1, 'TRANSFER_IN' => 1, 'OUT' => -1, 'TRANSFER_OUT' => -1, 'ADJUST' => 1];

    /** الرصيد المتاح لصنف في مخزن (أو كل المخازن إن لم يُحدَّد). */
    public function onHand(int $itemId, ?int $warehouseId = null): float
    {
        $rows = StockMovement::query()->where('item_id', $itemId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get(['movement_type', 'quantity']);
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (self::SIGN[$r->movement_type] ?? 0) * (float) $r->quantity;
        }
        return round($sum, 3);
    }

    public function totalOnHand(int $itemId): float
    {
        return $this->onHand($itemId, null);
    }

    /** استلام مخزني: يزيد الكمية ويحدّث متوسط التكلفة المرجّح. */
    public function receive(InventoryItem $item, int $warehouseId, float $qty, float $unitCost, string $date, array $ref = []): StockMovement
    {
        $this->assertQty($qty);
        return DB::transaction(function () use ($item, $warehouseId, $qty, $unitCost, $date, $ref) {
            $currentQty = $this->totalOnHand($item->id);
            $currentAvg = (float) $item->average_cost;
            $newQty = $currentQty + $qty;
            $newAvg = $newQty > 0 ? round((($currentQty * $currentAvg) + ($qty * $unitCost)) / $newQty, 3) : $unitCost;
            $item->update(['average_cost' => $newAvg]);

            return $this->move($item, $warehouseId, 'IN', $qty, $unitCost, $date, $ref);
        });
    }

    /** صرف مخزني بتكلفة المتوسط الجاري. */
    public function issue(InventoryItem $item, int $warehouseId, float $qty, string $date, array $ref = []): StockMovement
    {
        $this->assertQty($qty);
        $available = $this->onHand($item->id, $warehouseId);
        if ($qty > $available + 0.0005) {
            throw ValidationException::withMessages(['quantity' => ["الكمية المطلوبة ({$qty}) تتجاوز المتاح ({$available})"]]);
        }
        return $this->move($item, $warehouseId, 'OUT', $qty, (float) $item->average_cost, $date, $ref);
    }

    /** تحويل بين مخزنين. */
    public function transfer(InventoryItem $item, int $fromId, int $toId, float $qty, string $date): array
    {
        $this->assertQty($qty);
        if ($fromId === $toId) {
            throw ValidationException::withMessages(['warehouse' => ['مخزن المصدر والوجهة متطابقان']]);
        }
        $available = $this->onHand($item->id, $fromId);
        if ($qty > $available + 0.0005) {
            throw ValidationException::withMessages(['quantity' => ["الكمية تتجاوز المتاح في المخزن المصدر ({$available})"]]);
        }
        $cost = (float) $item->average_cost;
        return DB::transaction(function () use ($item, $fromId, $toId, $qty, $cost, $date) {
            $out = $this->move($item, $fromId, 'TRANSFER_OUT', $qty, $cost, $date, ['reference_type' => 'TRANSFER']);
            $in = $this->move($item, $toId, 'TRANSFER_IN', $qty, $cost, $date, ['reference_type' => 'TRANSFER']);
            return [$out, $in];
        });
    }

    /** تسوية جرد: delta موجب (زيادة) أو سالب (عجز) بتكلفة المتوسط. */
    public function adjust(InventoryItem $item, int $warehouseId, float $delta, string $date, ?string $note = null): StockMovement
    {
        if (abs($delta) < 0.0005) {
            throw ValidationException::withMessages(['quantity' => ['قيمة التسوية صفر']]);
        }
        if ($delta < 0) {
            $available = $this->onHand($item->id, $warehouseId);
            if (abs($delta) > $available + 0.0005) {
                throw ValidationException::withMessages(['quantity' => ["العجز يتجاوز المتاح ({$available})"]]);
            }
        }
        return $this->move($item, $warehouseId, 'ADJUST', $delta, (float) $item->average_cost, $date, ['description' => $note ?? 'تسوية جرد']);
    }

    /** تقييم المخزون: لكل صنف الكمية الإجمالية × متوسط التكلفة. */
    public function valuation(int $companyId): array
    {
        $items = InventoryItem::query()->where('company_id', $companyId)->orderBy('code')->get();
        $rows = [];
        $total = 0.0;
        foreach ($items as $item) {
            $qty = $this->totalOnHand($item->id);
            $value = round($qty * (float) $item->average_cost, 3);
            $total += $value;
            $rows[] = [
                'item_id' => $item->id, 'code' => $item->code, 'name' => $item->name,
                'quantity' => $qty, 'average_cost' => (float) $item->average_cost, 'value' => $value,
                'below_reorder' => $qty <= (float) $item->reorder_level,
            ];
        }
        return ['rows' => $rows, 'total_value' => round($total, 3)];
    }

    /** أرصدة صنف لكل مخزن. */
    public function itemStock(InventoryItem $item): array
    {
        $warehouses = Warehouse::query()->where('company_id', $item->company_id)->get();
        $rows = [];
        foreach ($warehouses as $w) {
            $rows[] = ['warehouse' => $w->name, 'quantity' => $this->onHand($item->id, $w->id)];
        }
        return ['item' => $item->code . ' — ' . $item->name, 'average_cost' => (float) $item->average_cost, 'warehouses' => $rows, 'total' => $this->totalOnHand($item->id)];
    }

    private function move(InventoryItem $item, int $warehouseId, string $type, float $qty, float $unitCost, string $date, array $ref): StockMovement
    {
        return StockMovement::query()->create([
            'company_id' => $item->company_id,
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'movement_type' => $type,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => round($qty * $unitCost, 3),
            'movement_date' => $date,
            'reference_type' => $ref['reference_type'] ?? 'MANUAL',
            'reference_id' => $ref['reference_id'] ?? null,
            'description' => $ref['description'] ?? null,
            'created_by' => $ref['created_by'] ?? null,
        ]);
    }

    private function assertQty(float $qty): void
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => ['الكمية يجب أن تكون موجبة']]);
        }
    }
}
