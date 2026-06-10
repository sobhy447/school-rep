<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'type', 'title', 'due_date', 'status', 'reference_type', 'reference_id', 'notes', 'auto', 'created_by'];
    protected function casts(): array { return ['due_date' => 'date', 'auto' => 'boolean']; }
}
