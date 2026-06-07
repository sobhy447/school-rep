<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SettlementAllocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'customer_account_id', 'trust_line_id', 'entitlement_line_id',
        'amount', 'allocation_date', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'allocation_date' => 'date'];
    }
}
