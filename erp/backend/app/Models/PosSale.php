<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fiscal_year_id', 'warehouse_id', 'cash_account_id', 'sale_number', 'sale_date', 'subtotal', 'tax_amount', 'total', 'paid', 'change_amount', 'journal_entry_id', 'created_by'];
    protected function casts(): array { return ['sale_date' => 'date', 'subtotal' => 'decimal:3', 'tax_amount' => 'decimal:3', 'total' => 'decimal:3', 'paid' => 'decimal:3', 'change_amount' => 'decimal:3']; }

    public function lines(): HasMany { return $this->hasMany(PosSaleLine::class); }
}
