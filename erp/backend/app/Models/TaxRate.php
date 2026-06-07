<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'name_en', 'rate', 'type', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_enabled' => 'boolean',
        ];
    }
}
