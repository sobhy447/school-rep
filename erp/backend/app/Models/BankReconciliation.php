<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'account_id', 'statement_date', 'statement_balance',
        'status', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'decimal:3', 'completed_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class, 'reconciliation_id');
    }
}
