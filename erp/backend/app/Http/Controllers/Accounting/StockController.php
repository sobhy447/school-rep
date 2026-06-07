<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(private InventoryService $service) {}

    private function ok($data, ?string $msg = null): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data]);
    }

    private function item(int $id): InventoryItem
    {
        return InventoryItem::query()->where('company_id', TenantContext::id())->findOrFail($id);
    }

    private function whRule(): array
    {
        return ['required', Rule::exists('warehouses', 'id')->where('company_id', TenantContext::id())];
    }

    public function receive(Request $request): JsonResponse
    {
        $d = $request->validate([
            'item_id' => ['required', 'integer'], 'warehouse_id' => $this->whRule(),
            'quantity' => ['required', 'numeric', 'gt:0'], 'unit_cost' => ['required', 'numeric', 'min:0'],
            'movement_date' => ['required', 'date'], 'description' => ['nullable', 'string'],
        ]);
        $m = $this->service->receive($this->item($d['item_id']), $d['warehouse_id'], (float) $d['quantity'], (float) $d['unit_cost'], $d['movement_date'], ['description' => $d['description'] ?? null, 'created_by' => $request->user()?->id]);
        return $this->ok($m, 'تم الاستلام');
    }

    public function issue(Request $request): JsonResponse
    {
        $d = $request->validate([
            'item_id' => ['required', 'integer'], 'warehouse_id' => $this->whRule(),
            'quantity' => ['required', 'numeric', 'gt:0'], 'movement_date' => ['required', 'date'], 'description' => ['nullable', 'string'],
        ]);
        $m = $this->service->issue($this->item($d['item_id']), $d['warehouse_id'], (float) $d['quantity'], $d['movement_date'], ['description' => $d['description'] ?? null, 'created_by' => $request->user()?->id]);
        return $this->ok($m, 'تم الصرف');
    }

    public function transfer(Request $request): JsonResponse
    {
        $d = $request->validate([
            'item_id' => ['required', 'integer'],
            'from_warehouse_id' => $this->whRule(), 'to_warehouse_id' => $this->whRule(),
            'quantity' => ['required', 'numeric', 'gt:0'], 'movement_date' => ['required', 'date'],
        ]);
        $r = $this->service->transfer($this->item($d['item_id']), $d['from_warehouse_id'], $d['to_warehouse_id'], (float) $d['quantity'], $d['movement_date']);
        return $this->ok($r, 'تم التحويل');
    }

    public function adjust(Request $request): JsonResponse
    {
        $d = $request->validate([
            'item_id' => ['required', 'integer'], 'warehouse_id' => $this->whRule(),
            'delta' => ['required', 'numeric'], 'movement_date' => ['required', 'date'], 'note' => ['nullable', 'string'],
        ]);
        $m = $this->service->adjust($this->item($d['item_id']), $d['warehouse_id'], (float) $d['delta'], $d['movement_date'], $d['note'] ?? null);
        return $this->ok($m, 'تمت التسوية');
    }

    public function valuation(): JsonResponse
    {
        return $this->ok($this->service->valuation(TenantContext::id()));
    }

    public function itemStock(int $id): JsonResponse
    {
        return $this->ok($this->service->itemStock($this->item($id)));
    }
}
