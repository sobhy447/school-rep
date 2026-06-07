<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\FixedAsset;
use App\Services\DepreciationService;
use App\Services\JournalService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FixedAssetController extends BaseCrudController
{
    protected string $modelClass = FixedAsset::class;
    protected string $orderBy = 'code';

    public function __construct(private DepreciationService $service, private JournalService $journal) {}

    protected function rules(Request $request, ?int $id = null): array
    {
        $cid = TenantContext::id();
        $acc = fn () => Rule::exists('accounts', 'id')->where('company_id', $cid);
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('fixed_assets', 'code')->where('company_id', $cid)->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'asset_account_id' => ['required', $acc()],
            'accumulated_depreciation_account_id' => ['required', $acc()],
            'depreciation_expense_account_id' => ['required', $acc()],
            'acquisition_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['STRAIGHT_LINE', 'DECLINING_BALANCE'])],
            'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $cid)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $cid)],
        ];
    }

    public function index(): JsonResponse
    {
        return $this->ok(FixedAsset::query()->orderBy('code')->get());
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(FixedAsset::query()->with('depreciationEntries')->findOrFail($id));
    }

    /** تشغيل إهلاك أصل واحد لفترة. */
    public function depreciate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'period_date' => ['required', 'date'],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())],
        ]);
        $asset = FixedAsset::query()->findOrFail($id);
        $entry = $this->service->run($asset, $data['period_date'], $data['fiscal_year_id'], $request->user()?->id);

        return $this->ok($entry, 'تم تشغيل الإهلاك');
    }

    /** تشغيل إهلاك كل الأصول لفترة. */
    public function depreciateAll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_date' => ['required', 'date'],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())],
        ]);
        $done = $this->service->runAll(TenantContext::id(), $data['period_date'], $data['fiscal_year_id'], $request->user()?->id);

        return $this->ok($done, 'تم تشغيل الإهلاك لعدد ' . count($done) . ' أصل');
    }

    /** استبعاد/بيع أصل. */
    public function dispose(Request $request, int $id): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate([
            'disposal_date' => ['required', 'date'],
            'proceeds' => ['nullable', 'numeric', 'min:0'],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)],
            'cash_account_id' => ['required_with:proceeds', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'gain_loss_account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
        ]);
        $asset = FixedAsset::query()->findOrFail($id);
        $asset = $this->service->dispose($asset, $data, $request->user()?->id);

        return $this->ok($asset, 'تم استبعاد الأصل');
    }

    /** جدول الإهلاك التقديري. */
    public function schedule(int $id): JsonResponse
    {
        $asset = FixedAsset::query()->findOrFail($id);
        return $this->ok(['asset' => $asset->code, 'monthly' => $this->service->monthlyAmount($asset), 'rows' => $this->service->schedule($asset)]);
    }
}
