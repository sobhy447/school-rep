<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Services\ChequeService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChequeController extends Controller
{
    public function __construct(private ChequeService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    public function index(): JsonResponse
    {
        return $this->ok(Cheque::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate([
            'type' => ['required', Rule::in(['INCOMING', 'OUTGOING'])],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)],
            'cheque_number' => ['required', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'party_account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'bank_account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'notes' => ['nullable', 'string'],
        ]);
        return $this->ok($this->service->register($cid, $request->user()?->id, $data), 'تم تسجيل الشيك', 201);
    }

    public function clear(Request $request, int $id): JsonResponse
    {
        $cheque = Cheque::query()->findOrFail($id);
        return $this->ok($this->service->clear($cheque, $request->user()?->id, $request->input('date')), 'تم تحصيل/صرف الشيك');
    }

    public function bounce(Request $request, int $id): JsonResponse
    {
        $cheque = Cheque::query()->findOrFail($id);
        return $this->ok($this->service->bounce($cheque, $request->user()?->id), 'تم تسجيل ارتداد الشيك');
    }
}
