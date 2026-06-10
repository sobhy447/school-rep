<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Services\SaleService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesInvoiceController extends Controller
{
    public function __construct(private SaleService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    private function rules(): array
    {
        $cid = TenantContext::id();
        return [
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)],
            'customer_account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $cid)],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('inventory_items', 'id')->where('company_id', $cid)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function index(): JsonResponse
    {
        return $this->ok(SalesInvoice::query()->withCount('lines')->orderByDesc('id')->get());
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(SalesInvoice::query()->with('lines')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $invoice = $this->service->create(TenantContext::id(), $request->user()?->id, $data);
        return $this->ok($invoice, 'تم حفظ فاتورة المبيعات', 201);
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $invoice = SalesInvoice::query()->findOrFail($id);
        return $this->ok($this->service->post($invoice, $request->user()?->id), 'تم ترحيل الفاتورة (مخزون + قيد)');
    }
}
