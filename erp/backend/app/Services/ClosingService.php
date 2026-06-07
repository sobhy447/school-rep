<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\FiscalYear;
use App\Models\JournalLine;
use App\Support\AccountType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الإقفال السنوي: قيود ختامية تلقائية.
 * 1) نقل الإيرادات والمصروفات إلى حساب النتيجة (Income Summary).
 * 2) نقل صافي النتيجة إلى الأرباح المحتجزة.
 * 3) إقفال السنة.
 */
class ClosingService
{
    private const EPSILON = 0.0005;

    public function __construct(private JournalService $journal) {}

    public function closeYear(int $companyId, ?int $userId, int $fiscalYearId): array
    {
        $fy = FiscalYear::query()->where('company_id', $companyId)->findOrFail($fiscalYearId);
        if (! $fy->isOpen()) {
            throw ValidationException::withMessages(['fiscal_year_id' => ['السنة مقفلة بالفعل']]);
        }

        $incomeSummaryId = (int) CompanySetting::get($companyId, 'income_summary_account_id', 0);
        $retainedId = (int) CompanySetting::get($companyId, 'retained_earnings_account_id', 0);
        if (! $incomeSummaryId || ! $retainedId) {
            throw ValidationException::withMessages([
                'settings' => ['يجب تعريف حساب النتيجة والأرباح المحتجزة في الإعدادات'],
            ]);
        }

        $balances = $this->leafBalances($companyId, $fiscalYearId);
        $date = $fy->end_date->toDateString();

        $revLines = [];
        $expLines = [];
        $totalRev = 0.0;
        $totalExp = 0.0;
        foreach ($balances as $accId => $info) {
            $bal = $info['balance'];
            if (abs($bal) < self::EPSILON) {
                continue;
            }
            if ($info['type'] === AccountType::REVENUE) {
                // رصيد الإيراد دائن بطبيعته ➜ نُقفله بجعله مديناً
                $revLines[] = ['account_id' => $accId, 'debit' => $bal, 'credit' => 0];
                $totalRev += $bal;
            } elseif ($info['type'] === AccountType::EXPENSE) {
                $expLines[] = ['account_id' => $accId, 'debit' => 0, 'credit' => $bal];
                $totalExp += $bal;
            }
        }

        $net = round($totalRev - $totalExp, 3); // موجب = ربح
        $created = [];

        return DB::transaction(function () use ($companyId, $userId, $fiscalYearId, $date, $revLines, $expLines, $net, $incomeSummaryId, $retainedId, $fy, &$created) {
            // قيد 1: نقل الإيرادات والمصروفات لحساب النتيجة
            if (! empty($revLines) || ! empty($expLines)) {
                $lines = array_merge($revLines, $expLines);
                if ($net > 0) {
                    $lines[] = ['account_id' => $incomeSummaryId, 'debit' => 0, 'credit' => $net];
                } elseif ($net < 0) {
                    $lines[] = ['account_id' => $incomeSummaryId, 'debit' => abs($net), 'credit' => 0];
                }
                $e1 = $this->journal->create($companyId, $userId, [
                    'fiscal_year_id' => $fiscalYearId,
                    'entry_date' => $date,
                    'description' => 'قيد إقفال — نقل الإيرادات والمصروفات لحساب النتيجة',
                    'lines' => $lines,
                ], 'CLOSING', false);
                $this->journal->post($this->journal->approve($e1, $userId), $userId);
                $created[] = $e1->entry_number;
            }

            // قيد 2: نقل صافي النتيجة من حساب النتيجة للأرباح المحتجزة
            if (abs($net) >= self::EPSILON) {
                $lines = $net > 0
                    ? [
                        ['account_id' => $incomeSummaryId, 'debit' => $net, 'credit' => 0],
                        ['account_id' => $retainedId, 'debit' => 0, 'credit' => $net],
                    ]
                    : [
                        ['account_id' => $retainedId, 'debit' => abs($net), 'credit' => 0],
                        ['account_id' => $incomeSummaryId, 'debit' => 0, 'credit' => abs($net)],
                    ];
                $e2 = $this->journal->create($companyId, $userId, [
                    'fiscal_year_id' => $fiscalYearId,
                    'entry_date' => $date,
                    'description' => 'قيد إقفال — ترحيل صافي النتيجة للأرباح المحتجزة',
                    'lines' => $lines,
                ], 'CLOSING', false);
                $this->journal->post($this->journal->approve($e2, $userId), $userId);
                $created[] = $e2->entry_number;
            }

            // إقفال السنة
            $fy->update(['status' => 'CLOSED', 'is_locked' => true]);

            return [
                'net_result' => $net,
                'result' => $net >= 0 ? 'ربح' : 'خسارة',
                'closing_entries' => $created,
                'fiscal_year' => $fy->fresh(),
            ];
        });
    }

    /**
     * أرصدة الحسابات الورقية (إيراد/مصروف) للسنة = الافتتاحي + حركة القيود المُرحَّلة.
     * @return array<int,array{type:string,balance:float}>
     */
    private function leafBalances(int $companyId, int $fiscalYearId): array
    {
        $accounts = Account::query()->where('company_id', $companyId)
            ->whereIn('type', [AccountType::REVENUE, AccountType::EXPENSE])
            ->withCount('children')->get()->filter(fn ($a) => $a->children_count === 0);

        // حركة القيود المُرحَّلة لهذه السنة
        $movements = JournalLine::query()
            ->where('journal_lines.company_id', $companyId)
            ->whereHas('journalEntry', fn ($q) => $q->where('fiscal_year_id', $fiscalYearId)->where('status', 'POSTED')->where('type', '!=', 'CLOSING'))
            ->selectRaw('account_id, SUM(debit) as d, SUM(credit) as c')
            ->groupBy('account_id')->get()->keyBy('account_id');

        $result = [];
        foreach ($accounts as $acc) {
            $mv = $movements->get($acc->id);
            $debit = (float) ($mv->d ?? 0);
            $credit = (float) ($mv->c ?? 0);
            // أضف الافتتاحي
            $opening = $acc->signedOpeningBalance();
            $movementSigned = AccountType::signedBalance($acc->type, $debit, $credit);
            $result[$acc->id] = [
                'type' => $acc->type,
                'balance' => round($opening + $movementSigned, 3),
            ];
        }
        return $result;
    }
}
