<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'name_en', 'symbol', 'is_base', 'exchange_rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'exchange_rate' => 'decimal:6',
        ];
    }
}
