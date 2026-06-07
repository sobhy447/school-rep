<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\SettlementAllocation;
use App\Support\AccountType;
use App\Support\TenantContext;

/**
 * التقارير المالية — كلها من القيود المُرحَّلة (POSTED) فقط + الأرصدة الافتتاحية.
 */
class ReportService
{
    /** حركة الحسابات المُرحَّلة (مدين/دائن لكل حساب) ضمن فترة اختيارية. */
    private function movements(int $companyId, ?string $from = null, ?string $to = null): array
    {
        return JournalLine::query()
            ->where('journal_lines.company_id', $companyId)
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                $q->where('status', 'POSTED');
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            })
            ->selectRaw('account_id, SUM(debit) as d, SUM(credit) as c')
            ->groupBy('account_id')->get()
            ->keyBy('account_id')
            ->map(fn ($r) => ['d' => (float) $r->d, 'c' => (float) $r->c])
            ->all();
    }

    /** الحسابات الورقية لشركة المستخدم. */
    private function leafAccounts(int $companyId)
    {
        return Account::query()->where('company_id', $companyId)
            ->withCount('children')->orderBy('code')->get()
            ->filter(fn ($a) => $a->children_count === 0)->values();
    }

    /**
     * ميزان المراجعة (بالأرصدة): لكل حساب ورقي مدين/دائن صافٍ.
     * المجموع المدين = المجموع الدائن (للدفاتر المتزنة).
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $companyId = TenantContext::id();
        $mv = $this->movements($companyId, $from, $to);
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($this->leafAccounts($companyId) as $acc) {
            $openDebit = $acc->opening_balance_type === 'DEBIT' ? (float) $acc->opening_balance : 0.0;
            $openCredit = $acc->opening_balance_type === 'CREDIT' ? (float) $acc->opening_balance : 0.0;
            $m = $mv[$acc->id] ?? ['d' => 0.0, 'c' => 0.0];
            $net = round(($openDebit + $m['d']) - ($openCredit + $m['c']), 3);
            if (abs($net) < 0.0005) {
                continue;
            }
            $debit = $net > 0 ? $net : 0.0;
            $credit = $net < 0 ? -$net : 0.0;
            $totalDebit += $debit;
            $totalCredit += $credit;
            $rows[] = [
                'account_id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name,
                'type' => $acc->type, 'debit' => round($debit, 3), 'credit' => round($credit, 3),
            ];
        }

        return [
            'rows' => $rows,
            'total_debit' => round($totalDebit, 3),
            'total_credit' => round($totalCredit, 3),
            'balanced' => abs($totalDebit - $totalCredit) < 0.0005,
        ];
    }

    /** صافي رصيد كل حساب ورقي حسب طبيعته (موجب). */
    private function netByType(int $companyId, ?string $from, ?string $to): array
    {
        $mv = $this->movements($companyId, $from, $to);
        $byType = [
            AccountType::ASSET => [], AccountType::LIABILITY => [],
            AccountType::EQUITY => [], AccountType::REVENUE => [], AccountType::EXPENSE => [],
        ];
        foreach ($this->leafAccounts($companyId) as $acc) {
            $opening = $acc->signedOpeningBalance();
            $m = $mv[$acc->id] ?? ['d' => 0.0, 'c' => 0.0];
            $balance = round($opening + AccountType::signedBalance($acc->type, $m['d'], $m['c']), 3);
            if (abs($balance) < 0.0005) {
                continue;
            }
            $byType[$acc->type][] = [
                'account_id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name, 'balance' => $balance,
            ];
        }
        return $byType;
    }

    /** قائمة الدخل: الإيرادات - المصروفات = صافي الربح/الخسارة. */
    public function incomeStatement(?string $from = null, ?string $to = null): array
    {
        $byType = $this->netByType(TenantContext::id(), $from, $to);
        $revenues = $byType[AccountType::REVENUE];
        $expenses = $byType[AccountType::EXPENSE];
        $totalRev = round(array_sum(array_column($revenues, 'balance')), 3);
        $totalExp = round(array_sum(array_column($expenses, 'balance')), 3);

        return [
            'revenues' => $revenues, 'total_revenues' => $totalRev,
            'expenses' => $expenses, 'total_expenses' => $totalExp,
            'net_profit' => round($totalRev - $totalExp, 3),
        ];
    }

    /** الميزانية العمومية: أصول = خصوم + حقوق ملكية (+ صافي نتيجة الفترة). */
    public function balanceSheet(?string $to = null): array
    {
        $byType = $this->netByType(TenantContext::id(), null, $to);
        $assets = $byType[AccountType::ASSET];
        $liabilities = $byType[AccountType::LIABILITY];
        $equity = $byType[AccountType::EQUITY];

        $totalAssets = round(array_sum(array_column($assets, 'balance')), 3);
        $totalLiab = round(array_sum(array_column($liabilities, 'balance')), 3);
        $totalEquity = round(array_sum(array_column($equity, 'balance')), 3);

        $income = $this->incomeStatement(null, $to);
        $netProfit = $income['net_profit'];
        $equityWithResult = round($totalEquity + $netProfit, 3);

        return [
            'assets' => $assets, 'total_assets' => $totalAssets,
            'liabilities' => $liabilities, 'total_liabilities' => $totalLiab,
            'equity' => $equity, 'total_equity' => $totalEquity,
            'net_profit' => $netProfit,
            'total_equity_with_result' => $equityWithResult,
            'total_liabilities_and_equity' => round($totalLiab + $equityWithResult, 3),
            'balanced' => abs($totalAssets - ($totalLiab + $equityWithResult)) < 0.0005,
        ];
    }

    /** الأستاذ العام لحساب: كل الحركات المُرحَّلة برصيد جارٍ. */
    public function generalLedger(int $accountId, ?string $from = null, ?string $to = null): array
    {
        $companyId = TenantContext::id();
        $account = Account::query()->where('company_id', $companyId)->findOrFail($accountId);

        $lines = JournalLine::query()
            ->where('company_id', $companyId)->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                $q->where('status', 'POSTED');
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            })
            ->with('journalEntry:id,entry_number,entry_date,description')->get()
            ->sortBy(fn ($l) => [optional($l->journalEntry)->entry_date, $l->id])->values();

        $running = $account->signedOpeningBalance();
        $opening = $running;
        $rows = [];
        foreach ($lines as $l) {
            $running = round($running + AccountType::signedBalance($account->type, (float) $l->debit, (float) $l->credit), 3);
            $rows[] = [
                'entry_number' => $l->journalEntry?->entry_number,
                'date' => optional($l->journalEntry?->entry_date)->toDateString(),
                'description' => $l->description ?: $l->journalEntry?->description,
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'balance' => $running,
            ];
        }
        return [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name],
            'opening_balance' => $opening, 'lines' => $rows, 'closing_balance' => $running,
        ];
    }

    /** التدفقات النقدية (مبسّط): صافي حركة حسابات النقدية/البنوك. */
    public function cashFlow(?string $from = null, ?string $to = null): array
    {
        $companyId = TenantContext::id();
        $mv = $this->movements($companyId, $from, $to);
        $cashAccounts = Account::query()->where('company_id', $companyId)->where('is_cash_or_bank', true)->get();

        $inflow = 0.0;
        $outflow = 0.0;
        $rows = [];
        foreach ($cashAccounts as $acc) {
            $m = $mv[$acc->id] ?? ['d' => 0.0, 'c' => 0.0];
            $inflow += $m['d'];
            $outflow += $m['c'];
            $rows[] = ['code' => $acc->code, 'name' => $acc->name,
                       'inflow' => round($m['d'], 3), 'outflow' => round($m['c'], 3),
                       'net' => round($m['d'] - $m['c'], 3)];
        }
        return [
            'accounts' => $rows,
            'total_inflow' => round($inflow, 3),
            'total_outflow' => round($outflow, 3),
            'net_cash_flow' => round($inflow - $outflow, 3),
        ];
    }

    /**
     * تقرير الدعاوى/الاستحقاقات على العملاء: كل حركة مدينة على عميل + المسدّد + الباقي.
     * detailed=true: سطر لكل حركة. detailed=false: تجميع لكل عميل.
     */
    public function claimsReport(bool $detailed = true, ?string $from = null, ?string $to = null): array
    {
        $companyId = TenantContext::id();
        $customerIds = Account::query()->where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')->pluck('name', 'id');

        $paid = SettlementAllocation::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw('entitlement_line_id, SUM(amount) as s')->groupBy('entitlement_line_id')
            ->pluck('s', 'entitlement_line_id')->map(fn ($v) => (float) $v)->all();

        $lines = JournalLine::query()
            ->where('company_id', $companyId)
            ->whereIn('account_id', $customerIds->keys())
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                $q->where('status', 'POSTED');
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            })
            ->with('journalEntry:id,entry_number,entry_date')->get();

        $detail = [];
        foreach ($lines as $l) {
            $amount = (float) $l->debit;
            $p = $paid[$l->id] ?? 0.0;
            $detail[] = [
                'customer_id' => $l->account_id,
                'customer' => $customerIds[$l->account_id] ?? null,
                'counterparty_name' => $l->counterparty_name,
                'entry_number' => $l->journalEntry?->entry_number,
                'date' => optional($l->journalEntry?->entry_date)->toDateString(),
                'description' => $l->description,
                'amount' => round($amount, 3),
                'paid' => round($p, 3),
                'remaining' => round($amount - $p, 3),
            ];
        }

        if ($detailed) {
            return ['rows' => $detail];
        }

        // تجميع لكل عميل
        $grouped = [];
        foreach ($detail as $d) {
            $cid = $d['customer_id'];
            $grouped[$cid] ??= ['customer_id' => $cid, 'customer' => $d['customer'], 'amount' => 0, 'paid' => 0, 'remaining' => 0];
            $grouped[$cid]['amount'] = round($grouped[$cid]['amount'] + $d['amount'], 3);
            $grouped[$cid]['paid'] = round($grouped[$cid]['paid'] + $d['paid'], 3);
            $grouped[$cid]['remaining'] = round($grouped[$cid]['remaining'] + $d['remaining'], 3);
        }
        return ['rows' => array_values($grouped)];
    }

    /** لوحة المؤشرات. */
    public function dashboard(): array
    {
        $bs = $this->balanceSheet();
        $income = $this->incomeStatement();
        $cf = $this->cashFlow();

        return [
            'total_assets' => $bs['total_assets'],
            'total_liabilities' => $bs['total_liabilities'],
            'total_equity_with_result' => $bs['total_equity_with_result'],
            'net_profit' => $income['net_profit'],
            'total_revenues' => $income['total_revenues'],
            'total_expenses' => $income['total_expenses'],
            'net_cash_flow' => $cf['net_cash_flow'],
            'balance_sheet_balanced' => $bs['balanced'],
        ];
    }
}
