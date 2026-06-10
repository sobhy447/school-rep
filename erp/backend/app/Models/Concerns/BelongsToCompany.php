<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عزل المستأجر: يُطبَّق على كل جدول يحمل company_id.
 * - Global Scope يحصر الاستعلامات على شركة المستخدم الحالي.
 * - عند الإنشاء يضبط company_id تلقائياً إن لم يُحدَّد.
 * مصدر الشركة الحالية: TenantContext (يُضبط من IdentifyCompany middleware).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = TenantContext::id();
            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->company_id) && TenantContext::id() !== null) {
                $model->company_id = TenantContext::id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
