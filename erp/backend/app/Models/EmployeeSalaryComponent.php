<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryComponent extends Model
{
    protected $fillable = ['company_id', 'employee_id', 'component_id', 'amount'];
    protected function casts(): array { return ['amount' => 'decimal:3']; }

    public function component(): BelongsTo { return $this->belongsTo(SalaryComponent::class, 'component_id'); }
}
