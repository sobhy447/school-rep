<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollLine extends Model
{
    protected $fillable = ['company_id', 'payroll_run_id', 'employee_id', 'basic', 'earnings', 'deductions', 'net', 'breakdown'];
    protected function casts(): array { return ['basic' => 'decimal:3', 'earnings' => 'decimal:3', 'deductions' => 'decimal:3', 'net' => 'decimal:3', 'breakdown' => 'array']; }
}
