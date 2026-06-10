<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'vendor_account_id', 'warehouse_id', 'invoice_number',
        'invoice_date', 'subtotal', 'tax_amount', 'total', 'status', 'journal_entry_id',
        'notes', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'subtotal' => 'decimal:3', 'tax_amount' => 'decimal:3', 'total' => 'decimal:3', 'posted_at' => 'datetime'];
    }

    public function lines(): HasMany { return $this->hasMany(PurchaseInvoiceLine::class, 'invoice_id'); }
}
