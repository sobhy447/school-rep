<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\InventoryItem;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryItemController extends BaseCrudController
{
    protected string $modelClass = InventoryItem::class;
    protected string $orderBy = 'code';

    protected function rules(Request $request, ?int $id = null): array
    {
        $cid = TenantContext::id();
        $acc = fn () => Rule::exists('accounts', 'id')->where('company_id', $cid);
        return [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('inventory_items', 'code')->where('company_id', $cid)->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'inventory_account_id' => ['nullable', $acc()],
            'cogs_account_id' => ['nullable', $acc()],
            'revenue_account_id' => ['nullable', $acc()],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $cid)],
            'is_active' => ['boolean'],
        ];
    }
}
