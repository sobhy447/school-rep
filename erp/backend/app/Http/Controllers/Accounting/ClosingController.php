<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\ClosingService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClosingController extends Controller
{
    public function __construct(private ClosingService $service) {}

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())],
        ]);
        $result = $this->service->closeYear(TenantContext::id(), $request->user()?->id, $data['fiscal_year_id']);

        return response()->json(['success' => true, 'message' => 'تم الإقفال السنوي', 'data' => $result]);
    }
}
