<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\SettlementService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettlementController extends Controller
{
    public function __construct(private SettlementService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    /** ملخّص كل العملاء (إجمالي الأمانات والديون). */
    public function summary(): JsonResponse
    {
        return $this->ok($this->service->summary());
    }

    /** عرض السداد لعميل: أمانات (يسار) + استحقاقات (يمين). */
    public function customer(int $accountId): JsonResponse
    {
        return $this->ok($this->service->customerView($accountId));
    }

    /** تنفيذ السداد (تخصيص أمانات لاستحقاقات). */
    public function allocate(Request $request, int $accountId): JsonResponse
    {
        $data = $request->validate([
            'allocation_date' => ['nullable', 'date'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.trust_line_id' => ['required', 'integer'],
            'allocations.*.entitlement_line_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $view = $this->service->allocate($accountId, $data['allocations'], $request->user()?->id, $data['allocation_date'] ?? null);

        return $this->ok($view, 'تم السداد');
    }
}
