<?php

namespace App\Services;

use App\Models\DepreciationEntry;
use App\Models\FixedAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق الإهلاك: حساب القسط (خط مستقيم/متناقص)، تشغيل الإهلاك بقيد تلقائي، والاستبعاد.
 */
class DepreciationService
{
    private const EPSILON = 0.0005;

    public function __construct(private JournalService $journal) {}

    /** قسط الإهلاك الشهري حسب الطريقة (لا يتجاوز ما يخفّض القيمة تحت التخريدية). */
    public function monthlyAmount(FixedAsset $asset): float
    {
        $bookValue = $asset->book_value;
        $depreciable = round($bookValue - (float) $asset->salvage_value, 3);
        if ($depreciable <= self::EPSILON) {
            return 0.0;
        }

        if ($asset->method === 'DECLINING_BALANCE') {
            $rate = (float) ($asset->declining_rate ?? 0) / 100 / 12;
            $amount = round($bookValue * $rate, 3);
        } else { // STRAIGHT_LINE
            $amount = round(((float) $asset->cost - (float) $asset->salvage_value) / max(1, (int) $asset->useful_life_months), 3);
        }

        return min($amount, $depreciable);
    }

    /** تشغيل إهلاك أصل لفترة: ينشئ قيداً مُرحَّلاً (مدين مصروف الإهلاك / دائن مجمع الإهلاك). */
    public function run(FixedAsset $asset, string $periodDate, ?int $fiscalYearId, ?int $userId): DepreciationEntry
    {
        if ($asset->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['status' => ['الأصل غير نشط']]);
        }
        if (! $fiscalYearId) {
            throw ValidationException::withMessages(['fiscal_year_id' => ['السنة المالية مطلوبة']]);
        }

        $exists = DepreciationEntry::query()->where('company_id', $asset->company_id)
            ->where('fixed_asset_id', $asset->id)->whereDate('period_date', $periodDate)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['period_date' => ['تم تشغيل الإهلاك لهذه الفترة من قبل']]);
        }

        $amount = $this->monthlyAmount($asset);
        if ($amount <= self::EPSILON) {
            throw ValidationException::withMessages(['amount' => ['الأصل مُهلك بالكامل — لا يوجد قسط']]);
        }

        return DB::transaction(function () use ($asset, $periodDate, $fiscalYearId, $userId, $amount) {
            $entry = $this->journal->create($asset->company_id, $userId, [
                'fiscal_year_id' => $fiscalYearId,
                'entry_date' => $periodDate,
                'description' => 'إهلاك الأصل ' . $asset->name,
                'lines' => [
                    ['account_id' => $asset->depreciation_expense_account_id, 'debit' => $amount, 'credit' => 0,
                     'cost_center_id' => $asset->cost_center_id],
                    ['account_id' => $asset->accumulated_depreciation_account_id, 'debit' => 0, 'credit' => $amount, 'is_main' => true],
                ],
            ], 'MANUAL', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $asset->increment('accumulated_depreciation', $amount);

            return DepreciationEntry::query()->create([
                'company_id' => $asset->company_id,
                'fixed_asset_id' => $asset->id,
                'period_date' => $periodDate,
                'amount' => $amount,
                'journal_entry_id' => $entry->id,
                'created_by' => $userId,
            ]);
        });
    }

    /** تشغيل إهلاك كل الأصول النشطة لفترة (يتخطّى المُشغَّل/المُهلك). */
    public function runAll(int $companyId, string $periodDate, ?int $fiscalYearId, ?int $userId): array
    {
        $assets = FixedAsset::query()->where('company_id', $companyId)->where('status', 'ACTIVE')->get();
        $done = [];
        foreach ($assets as $asset) {
            try {
                $this->run($asset, $periodDate, $fiscalYearId, $userId);
                $done[] = ['asset' => $asset->code, 'amount' => $this->monthlyAmount($asset->fresh())];
            } catch (ValidationException $e) {
                // تخطّي الأصول غير القابلة (مُشغَّلة/مُهلكة)
            }
        }
        return $done;
    }

    /**
     * استبعاد/بيع أصل: قيد يزيل التكلفة ومجمع الإهلاك، ويثبت النقدية والربح/الخسارة.
     */
    public function dispose(FixedAsset $asset, array $data, ?int $userId): FixedAsset
    {
        if ($asset->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['status' => ['الأصل مُستبعَد بالفعل']]);
        }
        $proceeds = round((float) ($data['proceeds'] ?? 0), 3);
        $bookValue = $asset->book_value;
        $gain = round($proceeds - $bookValue, 3);
        $cost = (float) $asset->cost;
        $accumulated = (float) $asset->accumulated_depreciation;

        $lines = [];
        if ($proceeds > 0) {
            $lines[] = ['account_id' => $data['cash_account_id'], 'debit' => $proceeds, 'credit' => 0, 'is_main' => true];
        }
        if ($accumulated > 0) {
            $lines[] = ['account_id' => $asset->accumulated_depreciation_account_id, 'debit' => $accumulated, 'credit' => 0, 'is_main' => true];
        }
        $lines[] = ['account_id' => $asset->asset_account_id, 'debit' => 0, 'credit' => $cost, 'is_main' => true];
        if (abs($gain) > self::EPSILON) {
            if ($gain > 0) {
                $lines[] = ['account_id' => $data['gain_loss_account_id'], 'debit' => 0, 'credit' => $gain, 'is_main' => true];
            } else {
                $lines[] = ['account_id' => $data['gain_loss_account_id'], 'debit' => abs($gain), 'credit' => 0, 'is_main' => true];
            }
        }

        return DB::transaction(function () use ($asset, $data, $userId, $lines, $proceeds) {
            $entry = $this->journal->create($asset->company_id, $userId, [
                'fiscal_year_id' => $data['fiscal_year_id'],
                'entry_date' => $data['disposal_date'],
                'description' => 'استبعاد الأصل ' . $asset->name,
                'lines' => $lines,
            ], 'MANUAL', false);
            $this->journal->post($this->journal->approve($entry, $userId), $userId);

            $asset->update([
                'status' => 'DISPOSED',
                'disposal_date' => $data['disposal_date'],
                'disposal_proceeds' => $proceeds,
            ]);
            return $asset->fresh();
        });
    }

    /** جدول إهلاك تقديري (معاينة). */
    public function schedule(FixedAsset $asset): array
    {
        $rows = [];
        $clone = clone $asset;
        $months = (int) $asset->useful_life_months;
        for ($i = 1; $i <= $months + 12; $i++) {
            $amount = $this->monthlyAmount($clone);
            if ($amount <= self::EPSILON) {
                break;
            }
            $clone->accumulated_depreciation = (float) $clone->accumulated_depreciation + $amount;
            $rows[] = [
                'month' => $i,
                'depreciation' => $amount,
                'accumulated' => round((float) $clone->accumulated_depreciation, 3),
                'book_value' => round((float) $clone->cost - (float) $clone->accumulated_depreciation, 3),
            ];
        }
        return $rows;
    }
}
