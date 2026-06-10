<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeReturn extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fiscal_year_id', 'type', 'party_account_id', 'warehouse_id', 'return_number', 'return_date', 'subtotal', 'tax_amount', 'total', 'journal_entry_id', 'notes', 'created_by'];
    protected function casts(): array { return ['return_date' => 'date', 'subtotal' => 'decimal:3', 'tax_amount' => 'decimal:3', 'total' => 'decimal:3']; }

    public function lines(): HasMany { return $this->hasMany(TradeReturnLine::class, 'return_id'); }
}
