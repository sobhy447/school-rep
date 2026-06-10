<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaimLine extends Model
{
    protected $fillable = [
        'company_id', 'claim_id', 'line_number', 'petty_cash_item_id', 'amount',
        'cost_center_id', 'cost_center_extra_id', 'expense_account_id', 'description',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3'];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'claim_id');
    }
}
