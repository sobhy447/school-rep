<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_en',
        'asset_account_id', 'accumulated_depreciation_account_id', 'depreciation_expense_account_id',
        'acquisition_date', 'cost', 'salvage_value', 'useful_life_months', 'method', 'declining_rate',
        'accumulated_depreciation', 'status', 'disposal_date', 'disposal_proceeds',
        'cost_center_id', 'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date', 'disposal_date' => 'date',
            'cost' => 'decimal:3', 'salvage_value' => 'decimal:3',
            'accumulated_depreciation' => 'decimal:3', 'disposal_proceeds' => 'decimal:3',
            'declining_rate' => 'decimal:4',
        ];
    }

    protected $appends = ['book_value'];

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    public function getBookValueAttribute(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 3);
    }
}
