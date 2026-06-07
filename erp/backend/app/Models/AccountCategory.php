<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountCategory extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'group', 'code', 'name', 'name_en', 'applies_to_type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_category_account');
    }
}
