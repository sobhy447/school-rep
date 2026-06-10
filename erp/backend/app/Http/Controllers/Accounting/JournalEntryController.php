<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalEntryController extends BaseCrudController
{
    protected string $modelClass = JournalEntry::class;
    protected string $orderBy = 'id';

    public function __construct(private JournalService $service) {}

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', TenantContext::id())],
            'entry_number' => ['nullable', 'string', 'max:50'],
            'entry_date' => ['required', 'date'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', TenantContext::id())],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', TenantContext::id())],
            'lines.*.cost_center_extra_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', TenantContext::id())],
            'lines.*.reference_number' => ['nullable', 'string', 'max:100'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function index(): JsonResponse
    {
        $items = JournalEntry::query()->where('type', 'MANUAL')
            ->withCount('lines')->orderByDesc('id')->get();

        return $this->ok($items);
    }

    public function show(int $id): JsonResponse
    {
        $entry = JournalEntry::query()->with('lines.account:id,code,name', 'lines.costCenterExtra:id,code,counterparty_name')
            ->findOrFail($id);

        return $this->ok($entry);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $entry = $this->service->create(TenantContext::id(), $request->user()?->id, $data, 'MANUAL');

        return $this->ok($entry, 'تم حفظ القيد (مسوّدة)', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::query()->findOrFail($id);
        $data = $request->validate($this->rules($request, $id));
        $entry = $this->service->update($entry, $data);

        return $this->ok($entry, 'تم تحديث القيد');
    }

    public function destroy(int $id): JsonResponse
    {
        $entry = JournalEntry::query()->findOrFail($id);
        if (! $entry->canEdit()) {
            return $this->ok(null, 'لا يمكن حذف قيد بعد اعتماده/ترحيله', 422);
        }
        $entry->lines()->delete();
        $entry->delete();

        return $this->ok(null, 'تم حذف القيد');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::query()->findOrFail($id);
        return $this->ok($this->service->approve($entry, $request->user()?->id), 'تم الاعتماد');
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::query()->findOrFail($id);
        return $this->ok($this->service->post($entry, $request->user()?->id), 'تم الترحيل');
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::query()->with('lines')->findOrFail($id);
        return $this->ok($this->service->reverse($entry, $request->user()?->id), 'تم عكس القيد');
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::query()->with('lines')->findOrFail($id);
        return $this->ok($this->service->duplicate($entry, $request->user()?->id), 'تم تكرار القيد', 201);
    }
}
