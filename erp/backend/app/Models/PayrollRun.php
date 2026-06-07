<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fiscal_year_id', 'period_year', 'period_month', 'run_date', 'status', 'total_earnings', 'total_deductions', 'net_total', 'journal_entry_id', 'created_by', 'posted_by', 'posted_at'];
    protected function casts(): array { return ['run_date' => 'date', 'total_earnings' => 'decimal:3', 'total_deductions' => 'decimal:3', 'net_total' => 'decimal:3', 'posted_at' => 'datetime']; }

    public function lines(): HasMany { return $this->hasMany(PayrollLine::class); }
}
