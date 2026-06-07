<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashItem extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_en', 'default_amount',
        'max_repeat', 'forbidden_months', 'is_permanent', 'expense_account_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:3',
            'is_permanent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
