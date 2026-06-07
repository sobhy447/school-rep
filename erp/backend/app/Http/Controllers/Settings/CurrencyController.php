<?php

namespace App\Http\Controllers\Settings;

use App\Models\Currency;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends BaseCrudController
{
    protected string $modelClass = Currency::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'size:3',
                Rule::unique('currencies', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'is_base' => ['boolean'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $data['code'] = strtoupper($data['code']);
        if (! empty($data['is_base'])) {
            $this->clearBase();
            $data['exchange_rate'] = 1; // العملة الأساسية سعرها 1 دائماً
        }
        $item = Currency::query()->create($data);

        return $this->ok($item, 'تم الإنشاء بنجاح', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);
        $data = $request->validate($this->rules($request, $id));
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        if (! empty($data['is_base'])) {
            $this->clearBase($id);
            $data['exchange_rate'] = 1;
        }
        $item->update($data);

        return $this->ok($item->fresh(), 'تم التحديث بنجاح');
    }

    /** عملة أساسية واحدة فقط لكل شركة. */
    private function clearBase(?int $exceptId = null): void
    {
        Currency::query()->where('is_base', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_base' => false]);
    }
}
