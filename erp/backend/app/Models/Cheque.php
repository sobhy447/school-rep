<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Cheque extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fiscal_year_id', 'type', 'cheque_number', 'bank_name', 'amount', 'issue_date', 'due_date', 'status', 'party_account_id', 'bank_account_id', 'register_entry_id', 'clear_entry_id', 'notes', 'created_by'];
    protected function casts(): array { return ['amount' => 'decimal:3', 'issue_date' => 'date', 'due_date' => 'date']; }
}
