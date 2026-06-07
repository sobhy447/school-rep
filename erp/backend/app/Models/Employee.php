<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'name_en', 'civil_id', 'hire_date', 'department', 'position', 'basic_salary', 'bank_account', 'is_active'];

    protected function casts(): array
    {
        return ['hire_date' => 'date', 'basic_salary' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function components(): HasMany { return $this->hasMany(EmployeeSalaryComponent::class); }
}
