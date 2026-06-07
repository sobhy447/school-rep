<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'branch_id', 'voucher_type_id', 'type',
        'entry_number', 'entry_date', 'currency_code', 'exchange_rate',
        'description', 'party_name', 'reference_number', 'status',
        'total_debit', 'total_credit', 'reversed_entry_id',
        'created_by', 'approved_by', 'posted_by', 'approved_at', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'total_debit' => 'decimal:3',
            'total_credit' => 'decimal:3',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_number');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function canEdit(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function canApprove(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function canPost(): bool
    {
        return $this->status === 'APPROVED';
    }
}
