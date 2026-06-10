<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    public function index(): JsonResponse
    {
        return $this->ok(PayrollRun::query()->withCount('lines')->orderByDesc('id')->get());
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(PayrollRun::query()->with('lines')->findOrFail($id));
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'run_date' => ['required', 'date'],
        ]);
        $run = $this->service->generate(TenantContext::id(), $request->user()?->id, $data);
        return $this->ok($run, 'تم توليد مسير الرواتب', 201);
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $run = PayrollRun::query()->findOrFail($id);
        return $this->ok($this->service->post($run, $request->user()?->id), 'تم ترحيل المسير (قيد رواتب)');
    }
}
