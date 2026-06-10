<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\JournalLine;
use App\Support\AccountType;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * التسوية البنكية — طبقة مطابقة: تأشير الحركات المُسوّاة ومقارنة الرصيد بكشف البنك.
 */
class BankReconciliationService
{
    private const EPSILON = 0.0005;

    /** إنشاء تسوية + إدراج حركات الحساب البنكي المُرحَّلة حتى تاريخ الكشف. */
    public function create(int $companyId, ?int $userId, array $data): BankReconciliation
    {
        $account = Account::query()->where('company_id', $companyId)->findOrFail($data['account_id']);
        if (! $account->is_cash_or_bank) {
            throw ValidationException::withMessages(['account_id' => ['الحساب ليس نقدية/بنك']]);
        }

        return DB::transaction(function () use ($companyId, $userId, $data) {
            $rec = BankReconciliation::query()->create([
                'company_id' => $companyId,
                'account_id' => $data['account_id'],
                'statement_date' => $data['statement_date'],
                'statement_balance' => $data['statement_balance'],
                'status' => 'DRAFT',
                'created_by' => $userId,
            ]);

            $lines = JournalLine::query()->where('company_id', $companyId)
                ->where('account_id', $data['account_id'])
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'POSTED')->whereDate('entry_date', '<=', $data['statement_date']))
                ->pluck('id');
            foreach ($lines as $lineId) {
                BankReconciliationLine::query()->create([
                    'company_id' => $companyId, 'reconciliation_id' => $rec->id,
                    'journal_line_id' => $lineId, 'is_cleared' => false,
                ]);
            }
            return $rec;
        });
    }

    /** عرض التسوية: الحركات + المُسوّى + الفرق. */
    public function view(BankReconciliation $rec): array
    {
        $account = $rec->account;
        $opening = $account->signedOpeningBalance();

        $lines = BankReconciliationLine::query()->where('reconciliation_id', $rec->id)
            ->with('journalLine.journalEntry:id,entry_number,entry_date,description')->get();

        $clearedMovement = 0.0;
        $rows = [];
        foreach ($lines as $rl) {
            $jl = $rl->journalLine;
            if (! $jl) continue;
            $debit = (float) $jl->debit;
            $credit = (float) $jl->credit;
            $signed = AccountType::signedBalance($account->type, $debit, $credit);
            if ($rl->is_cleared) {
                $clearedMovement += $signed;
            }
            $rows[] = [
                'line_id' => $rl->id,
                'journal_line_id' => $jl->id,
                'entry_number' => $jl->journalEntry?->entry_number,
                'date' => optional($jl->journalEntry?->entry_date)->toDateString(),
                'description' => $jl->description ?: $jl->journalEntry?->description,
                'debit' => $debit, 'credit' => $credit,
                'is_cleared' => $rl->is_cleared,
            ];
        }

        $reconciled = round($opening + $clearedMovement, 3);
        $difference = round((float) $rec->statement_balance - $reconciled, 3);

        return [
            'reconciliation' => [
                'id' => $rec->id, 'status' => $rec->status,
                'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name],
                'statement_date' => $rec->statement_date->toDateString(),
                'statement_balance' => (float) $rec->statement_balance,
            ],
            'opening_balance' => $opening,
            'lines' => $rows,
            'cleared_balance' => $reconciled,
            'difference' => $difference,
            'is_reconciled' => abs($difference) < self::EPSILON,
        ];
    }

    /**
     * استيراد كشف بنك (صفوف: amount موجب=إيداع/سالب=سحب) ومطابقتها تلقائياً بالحركات غير المؤشّرة.
     * @return array{matched:int, view:array}
     */
    public function importStatement(BankReconciliation $rec, array $rows): array
    {
        $account = $rec->account;
        $lines = BankReconciliationLine::query()->where('reconciliation_id', $rec->id)->where('is_cleared', false)
            ->with('journalLine')->get();

        $matched = 0;
        foreach ($rows as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 3);
            if (abs($amount) < 0.0005) {
                continue;
            }
            foreach ($lines as $rl) {
                if ($rl->is_cleared || ! $rl->journalLine) {
                    continue;
                }
                $signed = \App\Support\AccountType::signedBalance($account->type, (float) $rl->journalLine->debit, (float) $rl->journalLine->credit);
                if (abs($signed - $amount) < 0.0005) {
                    $rl->update(['is_cleared' => true]);
                    $matched++;
                    break;
                }
            }
        }
        return ['matched' => $matched, 'view' => $this->view($rec->fresh())];
    }

    /** تبديل حالة تأشير حركة. */
    public function toggle(BankReconciliation $rec, int $lineId, bool $cleared): array
    {
        $line = BankReconciliationLine::query()->where('reconciliation_id', $rec->id)->findOrFail($lineId);
        $line->update(['is_cleared' => $cleared]);
        return $this->view($rec->fresh());
    }

    /** إتمام التسوية (يتطلّب تطابق الرصيد). */
    public function complete(BankReconciliation $rec): array
    {
        $view = $this->view($rec);
        if (! $view['is_reconciled']) {
            throw ValidationException::withMessages(['difference' => ["لا يمكن الإتمام — يوجد فرق ({$view['difference']})"]]);
        }
        $rec->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        return $this->view($rec->fresh());
    }
}
