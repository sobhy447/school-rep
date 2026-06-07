<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\PettyCashItem;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PettyCashItemController extends BaseCrudController
{
    protected string $modelClass = PettyCashItem::class;
    protected string $orderBy = 'code';

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('petty_cash_items', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'max_repeat' => ['nullable', 'integer', 'min:1'],
            'forbidden_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_permanent' => ['boolean'],
            'expense_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', TenantContext::id())],
            'is_active' => ['boolean'],
        ];
    }
}
