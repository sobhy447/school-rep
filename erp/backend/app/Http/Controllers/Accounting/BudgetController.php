<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Budget;
use App\Models\JournalLine;
use App\Support\AccountType;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    private function ok($data, ?string $msg = null): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data]);
    }

    /** موازنات سنة مالية. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', TenantContext::id())]]);
        return $this->ok(Budget::query()->where('fiscal_year_id', $data['fiscal_year_id'])->get());
    }

    /** حفظ موازنة حساب (إنشاء/تحديث). */
    public function upsert(Request $request): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $cid)],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $budget = Budget::query()->updateOrCreate(
            ['company_id' => $cid, 'fiscal_year_id' => $data['fiscal_year_id'], 'account_id' => $data['account_id']],
            ['amount' => $data['amount'], 'notes' => $data['notes'] ?? null]
        );
        return $this->ok($budget, 'تم حفظ الموازنة');
    }

    /** تقرير الموازنة مقابل الفعلي. */
    public function report(Request $request): JsonResponse
    {
        $cid = TenantContext::id();
        $data = $request->validate(['fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('company_id', $cid)]]);
        $fyId = $data['fiscal_year_id'];

        $budgets = Budget::query()->where('fiscal_year_id', $fyId)->where('amount', '>', 0)->get();
        $rows = [];
        $totBudget = 0.0; $totActual = 0.0;
        foreach ($budgets as $b) {
            $acc = Account::query()->find($b->account_id);
            if (! $acc) continue;
            $mv = JournalLine::query()->where('company_id', $cid)->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'POSTED')->where('fiscal_year_id', $fyId))
                ->selectRaw('SUM(debit) d, SUM(credit) c')->first();
            $actual = AccountType::signedBalance($acc->type, (float) ($mv->d ?? 0), (float) ($mv->c ?? 0));
            $budget = (float) $b->amount;
            $variance = round($budget - $actual, 3);
            $pct = $budget > 0 ? round($actual / $budget * 100, 1) : 0;
            $totBudget += $budget; $totActual += $actual;
            $rows[] = ['account_id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name, 'type' => $acc->type,
                'budget' => round($budget, 3), 'actual' => round($actual, 3), 'variance' => $variance, 'used_pct' => $pct];
        }
        return $this->ok(['rows' => $rows, 'total_budget' => round($totBudget, 3), 'total_actual' => round($totActual, 3),
            'total_variance' => round($totBudget - $totActual, 3)]);
    }
}
