<?php

namespace App\Http\Controllers\Settings;

use App\Models\TaxRate;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxRateController extends BaseCrudController
{
    protected string $modelClass = TaxRate::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('tax_rates', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['sometimes', Rule::in(['VAT', 'WITHHOLDING', 'OTHER'])],
            'is_enabled' => ['boolean'],
        ];
    }
}
