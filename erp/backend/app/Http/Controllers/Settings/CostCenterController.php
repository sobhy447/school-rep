<?php

namespace App\Http\Controllers\Settings;

use App\Models\CostCenter;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostCenterController extends BaseCrudController
{
    protected string $modelClass = CostCenter::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('cost_centers', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable',
                Rule::exists('cost_centers', 'id')->where('company_id', TenantContext::id())],
            'is_active' => ['boolean'],
        ];
    }
}
