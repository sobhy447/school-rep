<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fiscal_year_id', 'account_id', 'amount', 'notes'];
    protected function casts(): array { return ['amount' => 'decimal:3']; }
}
