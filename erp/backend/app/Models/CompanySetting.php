<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'key', 'value'];

    /** قراءة قيمة إعداد لشركة (يتجاوز الـ scope عند الحاجة). */
    public static function get(int $companyId, string $key, $default = null)
    {
        $row = static::withoutGlobalScopes()->where('company_id', $companyId)->where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public static function put(int $companyId, string $key, $value): void
    {
        static::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['value' => $value]
        );
    }
}
