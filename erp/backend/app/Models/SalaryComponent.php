<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'type', 'default_amount', 'is_active'];
    protected function casts(): array { return ['default_amount' => 'decimal:3', 'is_active' => 'boolean']; }
}
