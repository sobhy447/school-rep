<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_en', 'unit', 'barcode',
        'purchase_price', 'sale_price', 'average_cost', 'reorder_level', 'cost_method',
        'inventory_account_id', 'cogs_account_id', 'revenue_account_id', 'tax_rate_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:3', 'sale_price' => 'decimal:3',
            'average_cost' => 'decimal:3', 'reorder_level' => 'decimal:3', 'is_active' => 'boolean',
        ];
    }
}
