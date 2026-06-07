<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق القيود والسندات — مصدر الحقيقة المحاسبي.
 * يضمن: حسابات ورقية فقط · مدين XOR دائن · توازن · سنة مفتوحة · ربط محاسبي تلقائي للسندات.
 */
class JournalService
{
    private const EPSILON = 0.0005;

    public function __construct(private NumberingService $numbering) {}

    /** السنة مفتوحة والتاريخ ضمن نطاقها. */
    public function assertFiscalYear(int $companyId, int $fiscalYearId, string $date): FiscalYear
    {
        $fy = FiscalYear::query()->where('company_id', $companyId)->findOrFail($fiscalYearId);
        if (! $fy->isOpen()) {
            throw ValidationException::withMessages(['fiscal_year_id' => ['السنة المالية مقفلة']]);
        }
        $start = $fy->start_date->toDateString();
        $end = $fy->end_date->toDateString();
        if ($date < $start || $date > $end) {
            throw ValidationException::withMessages([
                'entry_date' => ["تاريخ القيد خارج السنة المالية ({$start} ← {$end})"],
            ]);
        }
        return $fy;
    }

    /**
     * إنشاء قيد + أسطره داخل Transaction، مع كل التحقّقات المحاسبية.
     * كل سطر: account_id (أو يُستدعى من مركز التكلفة الإضافي)، debit XOR credit، مركز تكلفة أساسي.
     */
    public function create(int $companyId, ?int $userId, array $data, string $type = 'MANUAL'): JournalEntry
    {
        $this->assertFiscalYear($companyId, $data['fiscal_year_id'], $data['entry_date']);

        $lines = $this->prepareLines($data['lines'] ?? []);
        [$totalDebit, $totalCredit] = $this->assertBalanced($lines);

        return DB::transaction(function () use ($companyId, $userId, $data, $type, $lines, $totalDebit, $totalCredit) {
            $number = $data['entry_number']
                ?? $this->numbering->nextEntryNumber($companyId, $data['fiscal_year_id'], $type, $data['entry_date']);

            $entry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'voucher_type_id' => $data['voucher_type_id'] ?? null,
                'type' => $type,
                'entry_number' => $number,
                'entry_date' => $data['entry_date'],
                'currency_code' => $data['currency_code'] ?? 'KWD',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'description' => $data['description'] ?? null,
                'party_name' => $data['party_name'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'DRAFT',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => $userId,
            ]);

            $this->writeLines($entry, $lines);

            return $entry->load('lines');
        });
    }

    /** تحديث قيد (مسوّدة فقط) + إعادة بناء أسطره. */
    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        if (! $entry->canEdit()) {
            throw ValidationException::withMessages(['status' => ['لا يمكن تعديل قيد بعد اعتماده/ترحيله']]);
        }
        $this->assertFiscalYear($entry->company_id, $data['fiscal_year_id'] ?? $entry->fiscal_year_id,
            $data['entry_date'] ?? $entry->entry_date->toDateString());

        $lines = $this->prepareLines($data['lines'] ?? []);
        [$totalDebit, $totalCredit] = $this->assertBalanced($lines);

        return DB::transaction(function () use ($entry, $data, $lines, $totalDebit, $totalCredit) {
            $entry->update([
                'fiscal_year_id' => $data['fiscal_year_id'] ?? $entry->fiscal_year_id,
                'branch_id' => $data['branch_id'] ?? $entry->branch_id,
                'entry_date' => $data['entry_date'] ?? $entry->entry_date,
                'currency_code' => $data['currency_code'] ?? $entry->currency_code,
                'exchange_rate' => $data['exchange_rate'] ?? $entry->exchange_rate,
                'description' => $data['description'] ?? $entry->description,
                'party_name' => $data['party_name'] ?? $entry->party_name,
                'reference_number' => $data['reference_number'] ?? $entry->reference_number,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);
            $entry->lines()->delete();
            $this->writeLines($entry, $lines);

            return $entry->fresh('lines');
        });
    }

    public function approve(JournalEntry $entry, ?int $userId): JournalEntry
    {
        if (! $entry->canApprove()) {
            throw ValidationException::withMessages(['status' => ['لا يمكن اعتماد إلا المسوّدات']]);
        }
        $entry->update(['status' => 'APPROVED', 'approved_by' => $userId, 'approved_at' => now()]);
        return $entry->fresh();
    }

    public function post(JournalEntry $entry, ?int $userId): JournalEntry
    {
        if (! $entry->canPost()) {
            throw ValidationException::withMessages(['status' => ['يجب اعتماد القيد قبل الترحيل']]);
        }
        $this->assertFiscalYear($entry->company_id, $entry->fiscal_year_id, $entry->entry_date->toDateString());
        $entry->update(['status' => 'POSTED', 'posted_by' => $userId, 'posted_at' => now()]);
        return $entry->fresh();
    }

    /** عكس قيد مُرحَّل: ينشئ قيداً عكسياً مُرحَّلاً (مدين↔دائن) ويربطه بالأصل. */
    public function reverse(JournalEntry $entry, ?int $userId): JournalEntry
    {
        if ($entry->status !== 'POSTED') {
            throw ValidationException::withMessages(['status' => ['لا يُعكَس إلا قيد مُرحَّل']]);
        }
        $this->assertFiscalYear($entry->company_id, $entry->fiscal_year_id, $entry->entry_date->toDateString());

        return DB::transaction(function () use ($entry, $userId) {
            $number = $this->numbering->nextEntryNumber(
                $entry->company_id, $entry->fiscal_year_id, $entry->type, $entry->entry_date->toDateString()
            );
            $reversal = JournalEntry::query()->create([
                'company_id' => $entry->company_id,
                'fiscal_year_id' => $entry->fiscal_year_id,
                'branch_id' => $entry->branch_id,
                'type' => $entry->type,
                'entry_number' => $number . '-R',
                'entry_date' => $entry->entry_date->toDateString(),
                'currency_code' => $entry->currency_code,
                'exchange_rate' => $entry->exchange_rate,
                'description' => 'عكس قيد ' . $entry->entry_number,
                'status' => 'POSTED',
                'total_debit' => $entry->total_credit,
                'total_credit' => $entry->total_debit,
                'reversed_entry_id' => $entry->id,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);
            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'company_id' => $entry->company_id,
                    'line_number' => $line->line_number,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,   // معكوس
                    'credit' => $line->debit,
                    'cost_center_id' => $line->cost_center_id,
                    'cost_center_extra_id' => $line->cost_center_extra_id,
                    'reference_number' => $line->reference_number,
                    'description' => 'عكس: ' . $line->description,
                    'counterparty_name' => $line->counterparty_name,
                ]);
            }
            return $reversal->load('lines');
        });
    }

    /** نسخ قيد كمسوّدة جديدة. */
    public function duplicate(JournalEntry $entry, ?int $userId): JournalEntry
    {
        $data = [
            'fiscal_year_id' => $entry->fiscal_year_id,
            'branch_id' => $entry->branch_id,
            'entry_date' => $entry->entry_date->toDateString(),
            'currency_code' => $entry->currency_code,
            'exchange_rate' => $entry->exchange_rate,
            'description' => $entry->description,
            'party_name' => $entry->party_name,
            'reference_number' => $entry->reference_number,
            'lines' => $entry->lines->map(fn ($l) => [
                'account_id' => $l->account_id,
                'debit' => $l->debit,
                'credit' => $l->credit,
                'cost_center_id' => $l->cost_center_id,
                'cost_center_extra_id' => $l->cost_center_extra_id,
                'reference_number' => $l->reference_number,
                'description' => $l->description,
                'is_main' => $l->is_main,
            ])->all(),
        ];
        return $this->create($entry->company_id, $userId, $data, $entry->type);
    }

    /**
     * بناء سند (قبض/صرف/تحويل) ➜ قيد متوازن تلقائياً.
     * RECEIPT: الجانب الرئيسي مدين (نقدية/بنك). PAYMENT/TRANSFER: الجانب الرئيسي دائن.
     */
    public function createVoucher(int $companyId, ?int $userId, string $type, array $data): JournalEntry
    {
        $main = $data['main'];
        $mainAccount = $this->findLeafAccount($companyId, $main['account_id'] ?? null);
        if (! $mainAccount || ! $mainAccount->is_cash_or_bank) {
            throw ValidationException::withMessages([
                'main.account_id' => ['الجانب الرئيسي يجب أن يكون حساب نقدية أو بنك'],
            ]);
        }
        $amount = round((float) $main['amount'], 3);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['main.amount' => ['مبلغ السند يجب أن يكون موجباً']]);
        }

        $mainLine = [
            'account_id' => $mainAccount->id,
            'debit' => $type === 'RECEIPT' ? $amount : 0,
            'credit' => $type === 'RECEIPT' ? 0 : $amount,
            'cost_center_id' => $main['cost_center_id'] ?? null,   // اختياري للجانب الرئيسي
            'cost_center_extra_id' => $main['cost_center_extra_id'] ?? null,
            'reference_number' => $main['reference_number'] ?? null,
            'description' => $main['description'] ?? ($data['description'] ?? null),
            'is_main' => true,
        ];

        $lines = array_merge([$mainLine], $data['lines'] ?? []);

        $entryData = array_merge($data, ['lines' => $lines]);
        unset($entryData['main']);

        return $this->create($companyId, $userId, $entryData, $type);
    }

    // ───────────── مساعدات داخلية ─────────────

    /** يحضّر الأسطر: يستدعي الحساب من مركز التكلفة الإضافي، يجلب اسم الخصم، ويتحقّق. */
    private function prepareLines(array $rawLines): array
    {
        if (empty($rawLines)) {
            throw ValidationException::withMessages(['lines' => ['القيد يحتاج سطراً واحداً على الأقل']]);
        }

        $companyId = TenantContext::id();
        $prepared = [];
        foreach ($rawLines as $i => $line) {
            $lineNo = $line['line_number'] ?? ($i + 1);
            $isMain = (bool) ($line['is_main'] ?? false);
            $accountId = $line['account_id'] ?? null;
            $extraId = $line['cost_center_extra_id'] ?? null;
            $counterparty = $line['counterparty_name'] ?? null;

            // استدعاء الحساب واسم الخصم من مركز التكلفة الإضافي عند غياب الحساب
            if ($extraId) {
                $extra = CostCenter::query()->find($extraId);
                if ($extra) {
                    if (! $accountId && $extra->linked_account_id) {
                        $accountId = $extra->linked_account_id;
                    }
                    $counterparty = $counterparty ?: $extra->counterparty_name;
                }
            }

            $account = $this->findLeafAccount($companyId, $accountId);
            if (! $account) {
                throw ValidationException::withMessages([
                    'lines' => ["السطر {$lineNo}: حساب غير صالح أو غير ورقي (لا يقبل حركة)"],
                ]);
            }

            $debit = round((float) ($line['debit'] ?? 0), 3);
            $credit = round((float) ($line['credit'] ?? 0), 3);
            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages(['lines' => ["السطر {$lineNo}: المبالغ يجب أن تكون موجبة"]]);
            }
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages([
                    'lines' => ["السطر {$lineNo}: يجب إدخال مدين أو دائن (وليس الاثنين ولا صفر)"],
                ]);
            }

            // مركز التكلفة الأساسي إجباري لكل سطر عدا الجانب الرئيسي للسند
            if (! $isMain && empty($line['cost_center_id'])) {
                throw ValidationException::withMessages([
                    'lines' => ["السطر {$lineNo}: مركز التكلفة الأساسي إجباري"],
                ]);
            }

            $prepared[] = [
                'line_number' => $lineNo,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'cost_center_extra_id' => $extraId,
                'reference_number' => $line['reference_number'] ?? null,
                'description' => $line['description'] ?? null,
                'counterparty_name' => $counterparty,
                'is_main' => $isMain,
            ];
        }
        return $prepared;
    }

    /** التحقّق من التوازن: مجموع المدين = مجموع الدائن > 0. */
    private function assertBalanced(array $lines): array
    {
        $totalDebit = round(array_sum(array_column($lines, 'debit')), 3);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 3);

        if ($totalDebit <= 0) {
            throw ValidationException::withMessages(['lines' => ['إجمالي القيد يجب أن يكون موجباً']]);
        }
        if (abs($totalDebit - $totalCredit) > self::EPSILON) {
            throw ValidationException::withMessages([
                'lines' => ["القيد غير متوازن: مدين {$totalDebit} ≠ دائن {$totalCredit}"],
            ]);
        }
        return [$totalDebit, $totalCredit];
    }

    private function writeLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $line) {
            $entry->lines()->create([
                'company_id' => $entry->company_id,
                'line_number' => $line['line_number'],
                'account_id' => $line['account_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'cost_center_id' => $line['cost_center_id'],
                'cost_center_extra_id' => $line['cost_center_extra_id'],
                'reference_number' => $line['reference_number'],
                'description' => $line['description'],
                'counterparty_name' => $line['counterparty_name'],
                'is_main' => $line['is_main'],
            ]);
        }
    }

    /** حساب ورقي صالح ضمن الشركة (يقبل حركة وليس له أبناء). */
    private function findLeafAccount(?int $companyId, ?int $accountId): ?Account
    {
        if (! $accountId) {
            return null;
        }
        $account = Account::query()->withCount('children')->find($accountId);
        if (! $account || $account->children_count > 0 || ! $account->accepts_entries) {
            return null;
        }
        return $account;
    }
}
