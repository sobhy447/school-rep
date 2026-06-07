<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepreciationEntry extends Model
{
    protected $fillable = [
        'company_id', 'fixed_asset_id', 'period_date', 'amount', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['period_date' => 'date', 'amount' => 'decimal:3'];
    }
}
