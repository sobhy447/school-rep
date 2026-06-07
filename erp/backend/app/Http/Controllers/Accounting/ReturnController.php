<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\TradeReturn;
use App\Services\ReturnService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    public function index(Request $request): JsonResponse
    {
        $items = TradeReturn::query()->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->withCount('lines')->orderByDesc('id')->get();
        return $this->ok($items);
    }

    public function store(Request $request): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate([
            'type' => ['required', Rule::in(['SALES', 'PURCHASE'])],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)],
            'party_account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $cid)],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('inventory_items', 'id')->where('company_id', $cid)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        return $this->ok($this->service->create($cid, $request->user()?->id, $data['type'], $data), 'تم تسجيل المرتجع وترحيله', 201);
    }
}
