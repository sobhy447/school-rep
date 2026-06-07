<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\Employee;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends BaseCrudController
{
    protected string $modelClass = Employee::class;
    protected string $orderBy = 'code';

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('employees', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'civil_id' => ['nullable', 'string', 'max:50'],
            'hire_date' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(Employee::query()->with('components.component')->findOrFail($id));
    }

    /** تعيين/تحديث بدلات واستقطاعات الموظف. */
    public function setComponents(Request $request, int $id): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate([
            'components' => ['present', 'array'],
            'components.*.component_id' => ['required', Rule::exists('salary_components', 'id')->where('company_id', $cid)],
            'components.*.amount' => ['required', 'numeric', 'min:0'],
        ]);
        $emp = Employee::query()->findOrFail($id);
        $emp->components()->delete();
        foreach ($data['components'] as $c) {
            $emp->components()->create(['company_id' => $cid, 'component_id' => $c['component_id'], 'amount' => $c['amount']]);
        }
        return $this->ok($emp->fresh('components.component'), 'تم تحديث مكوّنات الراتب');
    }
}
