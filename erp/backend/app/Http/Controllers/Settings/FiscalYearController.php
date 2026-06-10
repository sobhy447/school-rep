<?php

namespace App\Http\Controllers\Settings;

use App\Models\FiscalYear;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FiscalYearController extends BaseCrudController
{
    protected string $modelClass = FiscalYear::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('fiscal_years', 'name')->where('company_id', TenantContext::id())->ignore($id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['sometimes', Rule::in(['OPEN', 'CLOSED'])],
            'is_locked' => ['boolean'],
        ];
    }

    /** إقفال السنة (منع الحركة). */
    public function lock(int $id): JsonResponse
    {
        $fy = FiscalYear::query()->findOrFail($id);
        $fy->update(['status' => 'CLOSED', 'is_locked' => true]);

        return $this->ok($fy->fresh(), 'تم إقفال السنة المالية');
    }

    /** فتح السنة. */
    public function unlock(int $id): JsonResponse
    {
        $fy = FiscalYear::query()->findOrFail($id);
        $fy->update(['status' => 'OPEN', 'is_locked' => false]);

        return $this->ok($fy->fresh(), 'تم فتح السنة المالية');
    }
}
