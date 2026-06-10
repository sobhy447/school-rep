<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceLine extends Model
{
    protected $fillable = ['company_id', 'invoice_id', 'item_id', 'line_number', 'quantity', 'unit_price', 'tax_rate', 'tax_amount', 'line_total', 'cost_amount'];
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:3', 'tax_rate' => 'decimal:4', 'tax_amount' => 'decimal:3', 'line_total' => 'decimal:3', 'cost_amount' => 'decimal:3']; }
}
