<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\SettlementAllocation;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نظام الأمانات/السداد — طبقة مطابقة منفصلة عن المحاسبة (لا تُنشئ قيوداً).
 * - الأمانة (Trust): حركة دائنة على حساب العميل (سند قبض).
 * - الاستحقاق (Entitlement): حركة مدينة على حساب العميل (أتعاب/مصروفات).
 * - السداد: تخصيص مبلغ من أمانة لاستحقاق، يحدّث المتبقي والألوان.
 */
class SettlementService
{
    private const EPSILON = 0.0005;

    /** أرصدة مخصّصة لكل سطر (trust_line_id/entitlement_line_id ➜ المجموع). */
    private function allocatedByTrust(int $companyId): array
    {
        return SettlementAllocation::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw('trust_line_id, SUM(amount) as s')->groupBy('trust_line_id')
            ->pluck('s', 'trust_line_id')->map(fn ($v) => (float) $v)->all();
    }

    private function allocatedByEntitlement(int $companyId): array
    {
        return SettlementAllocation::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw('entitlement_line_id, SUM(amount) as s')->groupBy('entitlement_line_id')
            ->pluck('s', 'entitlement_line_id')->map(fn ($v) => (float) $v)->all();
    }

    /** الحركات المُرحَّلة على حساب العميل (مدين/دائن). */
    private function postedLines(int $companyId, int $customerAccountId)
    {
        return JournalLine::query()
            ->where('company_id', $companyId)
            ->where('account_id', $customerAccountId)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'POSTED'))
            ->with('journalEntry:id,entry_number,entry_date,description')
            ->get();
    }

    /** عرض السداد لعميل: الأمانات (يسار) + الاستحقاقات (يمين). */
    public function customerView(int $customerAccountId): array
    {
        $companyId = TenantContext::id();
        $account = Account::query()->findOrFail($customerAccountId);

        $allocTrust = $this->allocatedByTrust($companyId);
        $allocEnt = $this->allocatedByEntitlement($companyId);
        $lines = $this->postedLines($companyId, $customerAccountId);

        $trusts = [];
        $entitlements = [];
        foreach ($lines as $line) {
            $credit = (float) $line->credit;
            $debit = (float) $line->debit;
            if ($credit > 0) {
                $allocated = $allocTrust[$line->id] ?? 0.0;
                $remaining = round($credit - $allocated, 3);
                if ($remaining > self::EPSILON) {
                    $trusts[] = $this->row($line, $credit, $allocated, $remaining, 'trust');
                }
            } elseif ($debit > 0) {
                $allocated = $allocEnt[$line->id] ?? 0.0;
                $remaining = round($debit - $allocated, 3);
                if ($remaining > self::EPSILON) {
                    $entitlements[] = $this->row($line, $debit, $allocated, $remaining, 'entitlement');
                }
            }
        }

        return [
            'customer' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name],
            'trusts' => $trusts,           // الجانب الأيسر (أمانات)
            'entitlements' => $entitlements, // الجانب الأيمن (استحقاقات)
            'total_trust_remaining' => round(array_sum(array_column($trusts, 'remaining')), 3),
            'total_entitlement_remaining' => round(array_sum(array_column($entitlements, 'remaining')), 3),
        ];
    }

    private function row(JournalLine $line, float $original, float $allocated, float $remaining, string $kind): array
    {
        // اللون: أحمر = لم يُسدّد منه شيء، أصفر = جزئي
        $color = abs($remaining - $original) < self::EPSILON ? 'RED' : 'YELLOW';
        return [
            'line_id' => $line->id,
            'entry_number' => $line->journalEntry?->entry_number,
            'date' => optional($line->journalEntry?->entry_date)->toDateString(),
            'description' => $line->description ?: $line->journalEntry?->description,
            'counterparty_name' => $line->counterparty_name,
            'original' => round($original, 3),
            'allocated' => round($allocated, 3),
            'remaining' => $remaining,
            'color' => $color,
        ];
    }

    /**
     * تنفيذ السداد: مصفوفة تخصيصات [{trust_line_id, entitlement_line_id, amount}].
     * يتحقّق: السطور تخص نفس العميل، والمبلغ لا يتجاوز المتبقي للأمانة ولا للاستحقاق.
     */
    public function allocate(int $customerAccountId, array $allocations, ?int $userId, ?string $date = null): array
    {
        $companyId = TenantContext::id();
        $date = $date ?: now()->toDateString();

        return DB::transaction(function () use ($companyId, $customerAccountId, $allocations, $userId, $date) {
            // أرصدة محدّثة داخل المعاملة
            $allocTrust = $this->allocatedByTrust($companyId);
            $allocEnt = $this->allocatedByEntitlement($companyId);

            $lineCache = [];
            $getLine = function ($id) use (&$lineCache, $companyId, $customerAccountId) {
                if (! isset($lineCache[$id])) {
                    $lineCache[$id] = JournalLine::query()
                        ->where('company_id', $companyId)
                        ->where('account_id', $customerAccountId)
                        ->find($id);
                }
                return $lineCache[$id];
            };

            foreach ($allocations as $i => $a) {
                $amount = round((float) ($a['amount'] ?? 0), 3);
                $trustId = (int) ($a['trust_line_id'] ?? 0);
                $entId = (int) ($a['entitlement_line_id'] ?? 0);

                if ($amount <= 0) {
                    throw ValidationException::withMessages(["allocations.$i.amount" => ['المبلغ يجب أن يكون موجباً']]);
                }
                $trust = $getLine($trustId);
                $ent = $getLine($entId);
                if (! $trust || (float) $trust->credit <= 0) {
                    throw ValidationException::withMessages(["allocations.$i.trust_line_id" => ['سطر أمانة غير صالح']]);
                }
                if (! $ent || (float) $ent->debit <= 0) {
                    throw ValidationException::withMessages(["allocations.$i.entitlement_line_id" => ['سطر استحقاق غير صالح']]);
                }

                $trustRemaining = round((float) $trust->credit - ($allocTrust[$trustId] ?? 0), 3);
                $entRemaining = round((float) $ent->debit - ($allocEnt[$entId] ?? 0), 3);

                if ($amount > $trustRemaining + self::EPSILON) {
                    throw ValidationException::withMessages([
                        "allocations.$i.amount" => ["المبلغ يتجاوز باقي الأمانة ({$trustRemaining})"],
                    ]);
                }
                if ($amount > $entRemaining + self::EPSILON) {
                    throw ValidationException::withMessages([
                        "allocations.$i.amount" => ["المبلغ يتجاوز باقي الاستحقاق ({$entRemaining})"],
                    ]);
                }

                SettlementAllocation::query()->create([
                    'company_id' => $companyId,
                    'customer_account_id' => $customerAccountId,
                    'trust_line_id' => $trustId,
                    'entitlement_line_id' => $entId,
                    'amount' => $amount,
                    'allocation_date' => $date,
                    'created_by' => $userId,
                ]);

                // تحديث الأرصدة محلياً لمنع تجاوز ضمن نفس الطلب
                $allocTrust[$trustId] = ($allocTrust[$trustId] ?? 0) + $amount;
                $allocEnt[$entId] = ($allocEnt[$entId] ?? 0) + $amount;
            }

            return $this->customerView($customerAccountId);
        });
    }

    /** ملخّص كل العملاء: إجمالي الأمانات والديون المتبقية. */
    public function summary(): array
    {
        $companyId = TenantContext::id();
        $customers = Account::query()->where('party_type', 'CUSTOMER')->get();

        $rows = [];
        foreach ($customers as $c) {
            $view = $this->customerView($c->id);
            if ($view['total_trust_remaining'] > 0 || $view['total_entitlement_remaining'] > 0) {
                $rows[] = [
                    'customer' => $view['customer'],
                    'trust_remaining' => $view['total_trust_remaining'],
                    'entitlement_remaining' => $view['total_entitlement_remaining'],
                ];
            }
        }
        return $rows;
    }
}
