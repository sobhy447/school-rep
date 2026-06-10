<?php

namespace App\Services;

use App\Models\JournalEntry;

/**
 * ترقيم تلقائي للمستندات لكل (شركة × سنة × نوع): PREFIX-YYYY-NNNN.
 */
class NumberingService
{
    private const PREFIX = [
        'MANUAL' => 'JV',
        'RECEIPT' => 'Q',
        'PAYMENT' => 'P',
        'TRANSFER' => 'T',
        'OPENING' => 'OP',
        'CLOSING' => 'CL',
    ];

    public function nextEntryNumber(int $companyId, int $fiscalYearId, string $type, string $date): string
    {
        $prefix = self::PREFIX[$type] ?? 'JV';
        $year = substr($date, 0, 4);

        $count = JournalEntry::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('type', $type)
            ->count();

        $seq = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$seq}";
    }
}
