<?php

namespace App\Http\Controllers\Settings;

use App\Models\VoucherType;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherTypeController extends BaseCrudController
{
    protected string $modelClass = VoucherType::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('voucher_types', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'direction' => ['required', Rule::in(['RECEIPT', 'PAYMENT', 'TRANSFER'])],
            'prefix' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ];
    }
}
