<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إعدادات الشركة (مفتاح/قيمة): حساب العهدة، حساب النتيجة، الأرباح المحتجزة... (خرائط الترحيل).
 */
class CompanySettingController extends Controller
{
    private const ALLOWED = [
        'petty_cash_account_id', 'income_summary_account_id', 'retained_earnings_account_id',
        'vat_input_account_id', 'vat_output_account_id',
    ];

    public function index(): JsonResponse
    {
        $all = CompanySetting::query()->pluck('value', 'key');
        return response()->json(['success' => true, 'message' => null, 'data' => $all]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);
        $companyId = TenantContext::id();
        foreach ($data['settings'] as $key => $value) {
            if (in_array($key, self::ALLOWED, true)) {
                CompanySetting::put($companyId, $key, (string) $value);
            }
        }
        return response()->json(['success' => true, 'message' => 'تم حفظ الإعدادات', 'data' => null]);
    }
}
