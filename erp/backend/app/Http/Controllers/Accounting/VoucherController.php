<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * السندات (قبض/صرف/تحويل) = قيود من أنواع خاصة، تُنشئ قيدها المحاسبي تلقائياً.
 * القبض: الجانب الرئيسي مدين (نقدية/بنك). الصرف/التحويل: الجانب الرئيسي دائن.
 * تُعتمد وتُرحَّل تلقائياً عند الحفظ (تدفّق الكاشير).
 */
class VoucherController extends BaseCrudController
{
    protected string $modelClass = JournalEntry::class;

    private const TYPES = ['RECEIPT', 'PAYMENT', 'TRANSFER'];

    public function __construct(private JournalService $service) {}

    protected function rules(Request $request, ?int $id = null): array
    {
        $companyId = TenantContext::id();
        return [
            'type' => ['required', Rule::in(self::TYPES)],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'voucher_type_id' => ['nullable', Rule::exists('voucher_types', 'id')->where('company_id', $companyId)],
            'entry_number' => ['nullable', 'string', 'max:50'],
            'entry_date' => ['required', 'date'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            // الجانب الرئيسي (نقدية/بنك)
            'main' => ['required', 'array'],
            'main.account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'main.amount' => ['required', 'numeric', 'gt:0'],
            'main.cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'main.cost_center_extra_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            // الأسطر المقابلة
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'lines.*.cost_center_extra_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'lines.*.reference_number' => ['nullable', 'string', 'max:100'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function index(): JsonResponse
    {
        $items = JournalEntry::query()->whereIn('type', self::TYPES)
            ->withCount('lines')->orderByDesc('id')->get();

        return $this->ok($items);
    }

    public function show(int $id): JsonResponse
    {
        $entry = JournalEntry::query()->whereIn('type', self::TYPES)
            ->with('lines.account:id,code,name', 'lines.costCenterExtra:id,code,counterparty_name')
            ->findOrFail($id);

        return $this->ok($entry);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $type = $data['type'];

        $entry = $this->service->createVoucher(TenantContext::id(), $request->user()?->id, $type, $data);
        // تدفّق السند: اعتماد + ترحيل تلقائي
        $entry = $this->service->approve($entry, $request->user()?->id);
        $entry = $this->service->post($entry, $request->user()?->id);

        return $this->ok($entry->load('lines'), 'تم حفظ السند وترحيله', 201);
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::query()->whereIn('type', self::TYPES)->with('lines')->findOrFail($id);
        return $this->ok($this->service->reverse($entry, $request->user()?->id), 'تم عكس السند');
    }
}
