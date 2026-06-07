<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\ExpenseClaim;
use App\Services\PettyCashService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseClaimController extends BaseCrudController
{
    protected string $modelClass = ExpenseClaim::class;

    public function __construct(private PettyCashService $service) {}

    protected function rules(Request $request, ?int $id = null): array
    {
        $cid = TenantContext::id();
        return [
            'claim_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.petty_cash_item_id' => ['required', Rule::exists('petty_cash_items', 'id')->where('company_id', $cid)],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $cid)],
            'lines.*.cost_center_extra_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $cid)],
            'lines.*.expense_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function index(): JsonResponse
    {
        return $this->ok(ExpenseClaim::query()->withCount('lines')->orderByDesc('id')->get());
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(ExpenseClaim::query()->with('lines')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $claim = $this->service->createClaim(TenantContext::id(), $request->user()?->id, $data);

        return $this->ok($claim, 'تم حفظ كشف العهدة', 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $claim = ExpenseClaim::query()->findOrFail($id);
        return $this->ok($this->service->approve($claim, $request->user()?->id), 'تم اعتماد الكشف');
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())],
            'entry_date' => ['nullable', 'date'],
        ]);
        $claim = ExpenseClaim::query()->findOrFail($id);
        $claim = $this->service->convert($claim, $request->user()?->id, $payload);

        return $this->ok($claim, 'تم تحويل الكشف إلى سند مُرحَّل');
    }
}
