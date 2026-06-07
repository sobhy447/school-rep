<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Models\PettyCashItem;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق العهد: حدود التكرار (منع نهائي)، وتحويل الكشف لقيد/سند مُرحَّل.
 */
class PettyCashService
{
    public function __construct(
        private NumberingService $numbering,
        private JournalService $journal,
    ) {}

    /** إنشاء كشف عهدة + أسطره، مع فحص حدود التكرار (منع نهائي). */
    public function createClaim(int $companyId, ?int $userId, array $data): ExpenseClaim
    {
        $lines = $data['lines'] ?? [];
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => ['الكشف يحتاج بنداً واحداً على الأقل']]);
        }

        foreach ($lines as $i => $line) {
            $this->assertRepeatAllowed($companyId, $line, $data['claim_date'], $i);
        }

        $total = round(array_sum(array_map(fn ($l) => (float) ($l['amount'] ?? 0), $lines)), 3);

        return DB::transaction(function () use ($companyId, $userId, $data, $lines, $total) {
            $number = $data['claim_number'] ?? $this->nextClaimNumber($companyId, $data['claim_date']);
            $claim = ExpenseClaim::query()->create([
                'company_id' => $companyId,
                'claim_number' => $number,
                'employee_id' => $data['employee_id'] ?? $userId,
                'claim_date' => $data['claim_date'],
                'description' => $data['description'] ?? null,
                'total_amount' => $total,
                'status' => 'DRAFT',
                'created_by' => $userId,
            ]);
            foreach ($lines as $i => $line) {
                ExpenseClaimLine::query()->create([
                    'company_id' => $companyId,
                    'claim_id' => $claim->id,
                    'line_number' => $line['line_number'] ?? ($i + 1),
                    'petty_cash_item_id' => $line['petty_cash_item_id'],
                    'amount' => $line['amount'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'cost_center_extra_id' => $line['cost_center_extra_id'] ?? null,
                    'expense_account_id' => $line['expense_account_id'] ?? $this->itemAccount($companyId, $line['petty_cash_item_id']),
                    'description' => $line['description'] ?? null,
                ]);
            }
            return $claim->load('lines');
        });
    }

    /** فحص حد التكرار: منع نهائي عند التجاوز أو ضمن الفترة الممنوعة. */
    public function assertRepeatAllowed(int $companyId, array $line, string $claimDate, int $index): void
    {
        $item = PettyCashItem::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->find($line['petty_cash_item_id'] ?? 0);
        if (! $item) {
            throw ValidationException::withMessages(["lines.$index.petty_cash_item_id" => ['بند صرف غير صالح']]);
        }
        $extraId = $line['cost_center_extra_id'] ?? null;

        // عدّ مرات استخدام نفس البند (لنفس مركز التكلفة الإضافي إن وُجد)
        $query = ExpenseClaimLine::query()->where('company_id', $companyId)
            ->where('petty_cash_item_id', $item->id)
            ->when($extraId, fn ($q) => $q->where('cost_center_extra_id', $extraId));

        // «دائم»: لا يتكرر نهائياً لنفس المركز
        if ($item->is_permanent && $query->clone()->exists()) {
            throw ValidationException::withMessages([
                "lines.$index" => ["البند «{$item->name}» دائم — لا يتكرر لنفس مركز التكلفة"],
            ]);
        }

        // الحد الأقصى لعدد المرات (خلال الفترة الممنوعة إن حُدّدت، وإلا إجمالاً)
        if ($item->max_repeat !== null) {
            $countQuery = $query->clone();
            if ($item->forbidden_months) {
                $since = Carbon::parse($claimDate)->subMonths($item->forbidden_months)->toDateString();
                $countQuery->whereHas('claim', fn ($q) => $q->where('claim_date', '>=', $since));
            }
            $used = $countQuery->count();
            if ($used >= $item->max_repeat) {
                throw ValidationException::withMessages([
                    "lines.$index" => ["البند «{$item->name}» تجاوز الحد الأقصى للتكرار ({$item->max_repeat})"],
                ]);
            }
        }
    }

    public function approve(ExpenseClaim $claim, ?int $userId): ExpenseClaim
    {
        if (! in_array($claim->status, ['DRAFT', 'SUBMITTED'], true)) {
            throw ValidationException::withMessages(['status' => ['لا يمكن اعتماد هذا الكشف']]);
        }
        $claim->update(['status' => 'APPROVED', 'approved_by' => $userId]);
        return $claim->fresh();
    }

    /**
     * تحويل الكشف المعتمد لقيد مُرحَّل: مدين مصروف كل بند / دائن حساب العهدة (الإجمالي).
     * حساب العهدة من إعدادات الشركة (petty_cash_account_id).
     */
    public function convert(ExpenseClaim $claim, ?int $userId, array $override = []): ExpenseClaim
    {
        if ($claim->status !== 'APPROVED') {
            throw ValidationException::withMessages(['status' => ['يجب اعتماد الكشف قبل التحويل']]);
        }
        $companyId = $claim->company_id;
        $custodyId = (int) CompanySetting::get($companyId, 'petty_cash_account_id', 0);
        if (! $custodyId) {
            throw ValidationException::withMessages(['settings' => ['حساب العهدة غير معرّف في الإعدادات']]);
        }

        $claim->load('lines');
        $fiscalYearId = $override['fiscal_year_id'] ?? null;
        if (! $fiscalYearId) {
            throw ValidationException::withMessages(['fiscal_year_id' => ['السنة المالية مطلوبة للتحويل']]);
        }

        // أسطر المدين (مصروفات) + سطر العهدة الدائن
        $lines = [];
        foreach ($claim->lines as $cl) {
            $lines[] = [
                'account_id' => $override['lines'][$cl->id]['expense_account_id'] ?? $cl->expense_account_id,
                'debit' => (float) $cl->amount,
                'credit' => 0,
                'cost_center_id' => $cl->cost_center_id,
                'cost_center_extra_id' => $cl->cost_center_extra_id,
                'description' => $cl->description,
            ];
        }
        $lines[] = [
            'account_id' => $custodyId,
            'debit' => 0,
            'credit' => (float) $claim->total_amount,
            'cost_center_id' => null,
            'is_main' => true, // الجانب الرئيسي (العهدة) لا يتطلب مركز تكلفة
        ];

        return DB::transaction(function () use ($claim, $userId, $companyId, $fiscalYearId, $lines, $override) {
            $entry = $this->journal->create($companyId, $userId, [
                'fiscal_year_id' => $fiscalYearId,
                'entry_date' => $override['entry_date'] ?? $claim->claim_date->toDateString(),
                'description' => 'تحويل عهدة ' . $claim->claim_number,
                'lines' => $lines,
            ], 'PAYMENT');
            $entry = $this->journal->approve($entry, $userId);
            $entry = $this->journal->post($entry, $userId);

            $claim->update([
                'status' => 'CONVERTED',
                'journal_entry_id' => $entry->id,
                'converted_by' => $userId,
            ]);
            return $claim->fresh('lines');
        });
    }

    private function itemAccount(int $companyId, int $itemId): ?int
    {
        return PettyCashItem::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->find($itemId)?->expense_account_id;
    }

    private function nextClaimNumber(int $companyId, string $date): string
    {
        $year = substr($date, 0, 4);
        $count = ExpenseClaim::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->whereYear('claim_date', $year)->count();
        return 'EXP-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
