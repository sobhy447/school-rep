<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseClaim extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'claim_number', 'employee_id', 'claim_date', 'description',
        'total_amount', 'status', 'journal_entry_id', 'created_by', 'approved_by', 'converted_by',
    ];

    protected function casts(): array
    {
        return ['claim_date' => 'date', 'total_amount' => 'decimal:3'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseClaimLine::class, 'claim_id')->orderBy('line_number');
    }
}
