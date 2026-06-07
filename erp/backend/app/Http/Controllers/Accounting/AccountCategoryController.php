<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\AccountCategory;
use App\Support\AccountType;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountCategoryController extends BaseCrudController
{
    protected string $modelClass = AccountCategory::class;
    protected string $orderBy = 'group';

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'group' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:50',
                Rule::unique('account_categories', 'code')
                    ->where('company_id', TenantContext::id())
                    ->where('group', $request->input('group'))
                    ->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'applies_to_type' => ['nullable', Rule::in(AccountType::ALL)],
            'is_active' => ['boolean'],
        ];
    }

    /** الفئات مجمّعة حسب المجموعة (للواجهة). */
    public function grouped(): JsonResponse
    {
        $grouped = AccountCategory::query()->orderBy('group')->orderBy('code')->get()->groupBy('group');

        return $this->ok($grouped);
    }
}
