<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الرواتب: توليد مسير لفترة (أساسي + بدلات - استقطاعات)، ثم ترحيل بقيد تلقائي:
 * مدين مصروف الرواتب / دائن الرواتب المستحقة (الصافي) / دائن الاستقطاعات المستحقة.
 */
class PayrollService
{
    public function __construct(private JournalService $journal) {}

    /** توليد مسير الرواتب لكل الموظفين النشطين لفترة. */
    public function generate(int $companyId, ?int $userId, array $data): PayrollRun
    {
        $exists = PayrollRun::query()->where('company_id', $companyId)
            ->where('period_year', $data['period_year'])->where('period_month', $data['period_month'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['period' => ['تم توليد مسير لهذه الفترة من قبل']]);
        }

        $employees = Employee::query()->where('company_id', $companyId)->where('is_active', true)
            ->with('components.component')->get();
        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['employees' => ['لا يوجد موظفون نشطون']]);
        }

        return DB::transaction(function () use ($companyId, $userId, $data, $employees) {
            $run = PayrollRun::query()->create([
                'company_id' => $companyId, 'fiscal_year_id' => $data['fiscal_year_id'],
                'period_year' => $data['period_year'], 'period_month' => $data['period_month'],
                'run_date' => $data['run_date'], 'status' => 'DRAFT', 'created_by' => $userId,
            ]);

            $totalEarn = 0; $totalDed = 0;
            foreach ($employees as $emp) {
                $basic = (float) $emp->basic_salary;
                $earnings = $basic; $deductions = 0; $breakdown = ['basic' => $basic];
                foreach ($emp->components as $ec) {
                    $amt = (float) $ec->amount;
                    if ($ec->component->type === 'EARNING') { $earnings += $amt; }
                    else { $deductions += $amt; }
                    $breakdown[$ec->component->code] = ['type' => $ec->component->type, 'amount' => $amt];
                }
                $net = round($earnings - $deductions, 3);
                $run->lines()->create([
                    'company_id' => $companyId, 'employee_id' => $emp->id,
                    'basic' => $basic, 'earnings' => round($earnings, 3), 'deductions' => round($deductions, 3),
                    'net' => $net, 'breakdown' => $breakdown,
                ]);
                $totalEarn += $earnings; $totalDed += $deductions;
            }
            $run->update([
                'total_earnings' => round($totalEarn, 3), 'total_deductions' => round($totalDed, 3),
                'net_total' => round($totalEarn - $totalDed, 3),
            ]);
            return $run->fresh('lines');
        });
    }

    /** ترحيل المسير ➜ قيد محاسبي. */
    public function post(PayrollRun $run, ?int $userId): PayrollRun
    {
        if ($run->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => ['المسير مُرحَّل بالفعل']]);
        }
        $cid = $run->company_id;
        $expense = (int) CompanySetting::get($cid, 'salary_expense_account_id', 0);
        $payable = (int) CompanySetting::get($cid, 'salaries_payable_account_id', 0);
        $deductPayable = (int) CompanySetting::get($cid, 'deductions_payable_account_id', 0);
        if (! $expense || ! $payable) {
            throw ValidationException::withMessages(['settings' => ['حسابات الرواتب غير معرّفة في الإعدادات']]);
        }
        if ((float) $run->total_deductions > 0 && ! $deductPayable) {
            throw ValidationException::withMessages(['settings' => ['حساب الاستقطاعات المستحقة غير معرّف']]);
        }

        return DB::transaction(function () use ($run, $userId, $expense, $payable, $deductPayable) {
            $lines = [
                ['account_id' => $expense, 'debit' => (float) $run->total_earnings, 'credit' => 0],
                ['account_id' => $payable, 'debit' => 0, 'credit' => (float) $run->net_total, 'is_main' => true],
            ];
            if ((float) $run->total_deductions > 0) {
                $lines[] = ['account_id' => $deductPayable, 'debit' => 0, 'credit' => (float) $run->total_deductions];
            }
            $entry = $this->journal->create($run->company_id, $userId, [
                'fiscal_year_id' => $run->fiscal_year_id,
                'entry_date' => $run->run_date->toDateString(),
                'description' => "مسير رواتب {$run->period_month}/{$run->period_year}",
                'lines' => $lines,
            ], 'MANUAL', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $run->update(['status' => 'POSTED', 'journal_entry_id' => $entry->id, 'posted_by' => $userId, 'posted_at' => now()]);
            return $run->fresh('lines');
        });
    }
}
