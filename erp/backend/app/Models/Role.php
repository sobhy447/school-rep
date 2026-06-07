<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['company_id', 'slug', 'name', 'name_en', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function syncPermissionKeys(array $keys): void
    {
        $ids = Permission::whereIn('key', $keys)->pluck('id')->all();
        $this->permissions()->sync($ids);
    }
}
