<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'status', 'is_locked'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_locked' => 'boolean',
        ];
    }

    /** هل السنة مفتوحة للحركة؟ */
    public function isOpen(): bool
    {
        return $this->status === 'OPEN' && ! $this->is_locked;
    }
}
