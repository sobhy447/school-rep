<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دورة الشيكات: التسجيل ينشئ قيداً، والتحصيل ينشئ قيداً للبنك، والارتداد يعكس التسجيل.
 * - وارد: تسجيل (مدين شيكات تحت التحصيل / دائن العميل) ➜ تحصيل (مدين البنك / دائن تحت التحصيل).
 * - صادر: تسجيل (مدين المورد / دائن شيكات الدفع) ➜ صرف (مدين شيكات الدفع / دائن البنك).
 */
class ChequeService
{
    public function __construct(private JournalService $journal) {}

    public function register(int $companyId, ?int $userId, array $data): Cheque
    {
        $collection = (int) CompanySetting::get($companyId, 'cheques_collection_account_id', 0);
        $payable = (int) CompanySetting::get($companyId, 'cheques_payable_account_id', 0);
        if ($data['type'] === 'INCOMING' && ! $collection) {
            throw ValidationException::withMessages(['settings' => ['حساب شيكات تحت التحصيل غير معرّف']]);
        }
        if ($data['type'] === 'OUTGOING' && ! $payable) {
            throw ValidationException::withMessages(['settings' => ['حساب شيكات الدفع غير معرّف']]);
        }
        $amount = round((float) $data['amount'], 3);

        return DB::transaction(function () use ($companyId, $userId, $data, $amount, $collection, $payable) {
            $cheque = Cheque::query()->create($data + ['company_id' => $companyId, 'status' => 'PENDING', 'created_by' => $userId]);
            $lines = $data['type'] === 'INCOMING'
                ? [['account_id' => $collection, 'debit' => $amount, 'credit' => 0, 'is_main' => true],
                   ['account_id' => $data['party_account_id'], 'debit' => 0, 'credit' => $amount, 'is_main' => true]]
                : [['account_id' => $data['party_account_id'], 'debit' => $amount, 'credit' => 0, 'is_main' => true],
                   ['account_id' => $payable, 'debit' => 0, 'credit' => $amount, 'is_main' => true]];
            $entry = $this->postEntry($companyId, $userId, $data['fiscal_year_id'], $data['issue_date'], 'تسجيل شيك ' . $data['cheque_number'], $lines);
            $cheque->update(['register_entry_id' => $entry->id]);
            return $cheque->fresh();
        });
    }

    public function clear(Cheque $cheque, ?int $userId, ?string $date = null): Cheque
    {
        if ($cheque->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => ['الشيك ليس قيد الانتظار']]);
        }
        $companyId = $cheque->company_id;
        $collection = (int) CompanySetting::get($companyId, 'cheques_collection_account_id', 0);
        $payable = (int) CompanySetting::get($companyId, 'cheques_payable_account_id', 0);
        $amount = (float) $cheque->amount;
        $date = $date ?: now()->toDateString();

        return DB::transaction(function () use ($cheque, $userId, $companyId, $collection, $payable, $amount, $date) {
            $lines = $cheque->type === 'INCOMING'
                ? [['account_id' => $cheque->bank_account_id, 'debit' => $amount, 'credit' => 0, 'is_main' => true],
                   ['account_id' => $collection, 'debit' => 0, 'credit' => $amount, 'is_main' => true]]
                : [['account_id' => $payable, 'debit' => $amount, 'credit' => 0, 'is_main' => true],
                   ['account_id' => $cheque->bank_account_id, 'debit' => 0, 'credit' => $amount, 'is_main' => true]];
            $entry = $this->postEntry($companyId, $userId, $cheque->fiscal_year_id, $date, 'تحصيل/صرف شيك ' . $cheque->cheque_number, $lines);
            $cheque->update(['status' => 'CLEARED', 'clear_entry_id' => $entry->id]);
            return $cheque->fresh();
        });
    }

    public function bounce(Cheque $cheque, ?int $userId): Cheque
    {
        if ($cheque->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => ['لا يمكن ارتداد شيك غير منتظر']]);
        }
        return DB::transaction(function () use ($cheque, $userId) {
            if ($cheque->register_entry_id) {
                $entry = \App\Models\JournalEntry::query()->with('lines')->find($cheque->register_entry_id);
                if ($entry && $entry->status === 'POSTED') {
                    $this->journal->reverse($entry, $userId); // قيد عكسي
                }
            }
            $cheque->update(['status' => 'BOUNCED']);
            return $cheque->fresh();
        });
    }

    private function postEntry(int $companyId, ?int $userId, int $fyId, string $date, string $desc, array $lines)
    {
        $entry = $this->journal->create($companyId, $userId, [
            'fiscal_year_id' => $fyId, 'entry_date' => $date, 'description' => $desc, 'lines' => $lines,
        ], 'MANUAL', false);
        return $this->journal->post($this->journal->approve($entry, $userId), $userId);
    }
}
