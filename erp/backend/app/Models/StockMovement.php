<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'company_id', 'item_id', 'warehouse_id', 'movement_type', 'quantity',
        'unit_cost', 'total_cost', 'movement_date', 'reference_type', 'reference_id',
        'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'unit_cost' => 'decimal:3', 'total_cost' => 'decimal:3',
            'movement_date' => 'date',
        ];
    }
}
