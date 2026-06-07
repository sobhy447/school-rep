<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\PosSale;
use App\Services\PosService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(private PosService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    public function index(): JsonResponse
    {
        return $this->ok(PosSale::query()->withCount('lines')->orderByDesc('id')->limit(50)->get());
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(PosSale::query()->with('lines')->findOrFail($id));
    }

    public function checkout(Request $request): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $cid)],
            'cash_account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'sale_date' => ['required', 'date'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('inventory_items', 'id')->where('company_id', $cid)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $sale = $this->service->checkout($cid, $request->user()?->id, $data);
        return $this->ok($sale, 'تم البيع وترحيله', 201);
    }
}
