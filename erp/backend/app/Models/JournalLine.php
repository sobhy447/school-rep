<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'company_id', 'journal_entry_id', 'line_number', 'account_id',
        'debit', 'credit', 'cost_center_id', 'cost_center_extra_id',
        'reference_number', 'description', 'counterparty_name', 'is_main',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:3',
            'credit' => 'decimal:3',
            'is_main' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function costCenterExtra(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_extra_id');
    }
}
