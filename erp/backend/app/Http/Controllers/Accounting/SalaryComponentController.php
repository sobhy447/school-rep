<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\SalaryComponent;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalaryComponentController extends BaseCrudController
{
    protected string $modelClass = SalaryComponent::class;
    protected string $orderBy = 'code';

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('salary_components', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['EARNING', 'DEDUCTION'])],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
