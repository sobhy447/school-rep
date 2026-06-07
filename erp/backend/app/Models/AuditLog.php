<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'user_name', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values'];
    protected function casts(): array { return ['old_values' => 'array', 'new_values' => 'array']; }
}
