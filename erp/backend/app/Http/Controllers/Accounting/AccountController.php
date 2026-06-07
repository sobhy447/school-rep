<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\Account;
use App\Models\JournalLine;
use App\Services\AccountService;
use App\Support\AccountType;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends BaseCrudController
{
    protected string $modelClass = Account::class;
    protected string $orderBy = 'code';

    public function __construct(private AccountService $service) {}

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('accounts', 'code')->where('company_id', TenantContext::id())->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(AccountType::ALL)],
            'parent_id' => ['nullable',
                Rule::exists('accounts', 'id')->where('company_id', TenantContext::id())],
            'opening_balance' => ['sometimes', 'numeric'],
            'opening_balance_type' => ['sometimes', Rule::in(['DEBIT', 'CREDIT'])],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'cost_center_id' => ['nullable',
                Rule::exists('cost_centers', 'id')->where('company_id', TenantContext::id())],
            'tax_rate_id' => ['nullable',
                Rule::exists('tax_rates', 'id')->where('company_id', TenantContext::id())],
            'cost_center_required' => ['boolean'],
            'meta' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [Rule::exists('account_categories', 'id')->where('company_id', TenantContext::id())],
            'is_active' => ['boolean'],
        ];
    }

    public function index(): JsonResponse
    {
        $items = Account::query()->withCount('children')->with('categories:id,group,code,name')
            ->orderBy('code')->get();

        return $this->ok($items);
    }

    public function show(int $id): JsonResponse
    {
        $item = Account::query()->withCount('children')->with('categories')->findOrFail($id);
        $item->setAttribute('balance', $this->service->balanceOf($item));

        return $this->ok($item);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $this->service->assertTypeMatchesParent($data['type'], $data['parent_id'] ?? null);

        $account = Account::query()->create($data);
        $this->service->refreshParentPostable($account->parent_id);
        if ($request->has('category_ids')) {
            $account->categories()->sync($request->input('category_ids', []));
        }

        return $this->ok($account->load('categories'), 'تم إنشاء الحساب', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = Account::query()->findOrFail($id);
        $data = $request->validate($this->rules($request, $id));

        $newParent = $data['parent_id'] ?? null;
        $this->service->assertNoCycle($account, $newParent);
        $this->service->assertTypeMatchesParent($data['type'] ?? $account->type, $newParent);

        $account->update($data);
        $this->service->refreshParentPostable($account->parent_id);
        if ($request->has('category_ids')) {
            $account->categories()->sync($request->input('category_ids', []));
        }

        return $this->ok($account->fresh('categories'), 'تم تحديث الحساب');
    }

    public function destroy(int $id): JsonResponse
    {
        $account = Account::query()->withCount('children')->findOrFail($id);
        if ($account->children_count > 0) {
            return $this->ok(null, 'لا يمكن حذف حساب له حسابات فرعية', 422);
        }
        $account->delete();

        return $this->ok(null, 'تم حذف الحساب');
    }

    /** قائمة الأطراف (عملاء/موردون) — وهم حسابات في الشجرة. */
    public function parties(Request $request): JsonResponse
    {
        $type = $request->query('type'); // CUSTOMER / VENDOR
        $items = Account::query()
            ->when($type, fn ($q) => $q->where('party_type', $type),
                fn ($q) => $q->whereNotNull('party_type'))
            ->orderBy('code')->get();

        return $this->ok($items);
    }

    /** كشف حساب: الرصيد الافتتاحي + الحركات المُرحَّلة + الرصيد الجاري. */
    public function statement(int $id): JsonResponse
    {
        $account = Account::query()->findOrFail($id);

        $lines = JournalLine::query()
            ->where('company_id', TenantContext::id())
            ->where('account_id', $id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'POSTED'))
            ->with('journalEntry:id,entry_number,entry_date,description')
            ->get()
            ->sortBy(fn ($l) => [optional($l->journalEntry)->entry_date, $l->id])
            ->values();

        $running = $account->signedOpeningBalance();
        $opening = $running;
        $rows = [];
        foreach ($lines as $l) {
            $debit = (float) $l->debit;
            $credit = (float) $l->credit;
            $running = round($running + AccountType::signedBalance($account->type, $debit, $credit), 3);
            $rows[] = [
                'entry_number' => $l->journalEntry?->entry_number,
                'date' => optional($l->journalEntry?->entry_date)->toDateString(),
                'description' => $l->description ?: $l->journalEntry?->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
            ];
        }

        return $this->ok([
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name,
                          'type' => $account->type, 'normal_balance' => $account->normal_balance],
            'opening_balance' => $opening,
            'lines' => $rows,
            'closing_balance' => $running,
        ]);
    }

    /** بحث ذكي عن الحسابات الورقية (F1 في شاشة القيد). */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $items = Account::query()->withCount('children')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->where('accepts_entries', true)
            ->orderBy('code')->limit(20)->get()
            ->filter(fn ($a) => $a->children_count === 0)
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name,
                              'type' => $a->type, 'is_cash_or_bank' => $a->is_cash_or_bank])
            ->values();

        return $this->ok($items);
    }

    /** الشجرة الكاملة مع الأرصدة المجمّعة. */
    public function tree(): JsonResponse
    {
        return $this->ok($this->service->tree());
    }

    /** رصيد حساب مجمّعاً (Bottom-up). */
    public function balance(int $id): JsonResponse
    {
        $account = Account::query()->findOrFail($id);

        return $this->ok([
            'account_id' => $account->id,
            'code' => $account->code,
            'balance' => $this->service->balanceOf($account),
            'normal_balance' => $account->normal_balance,
        ]);
    }

    /** استيراد شجرة حسابات (معاينة أو تنفيذ). */
    public function import(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'commit' => ['boolean'],
        ]);

        $result = $this->service->import($payload['rows'], (bool) ($payload['commit'] ?? false));

        $message = ! empty($result['errors'])
            ? 'توجد أخطاء — لم يتم الاستيراد'
            : ((bool) ($payload['commit'] ?? false) ? "تم استيراد {$result['imported']} حساباً" : 'المعاينة جاهزة');

        return $this->ok($result, $message, empty($result['errors']) ? 200 : 422);
    }
}
