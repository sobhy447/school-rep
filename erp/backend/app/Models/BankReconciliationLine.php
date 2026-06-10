<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationLine extends Model
{
    protected $fillable = ['company_id', 'reconciliation_id', 'journal_line_id', 'is_cleared'];

    protected function casts(): array
    {
        return ['is_cleared' => 'boolean'];
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}
