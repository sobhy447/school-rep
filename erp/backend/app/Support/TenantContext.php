<?php

namespace App\Support;

/**
 * سياق المستأجر الحالي لكل طلب (Request-scoped).
 * يُضبط من IdentifyCompany middleware، وتقرأه BelongsToCompany.
 */
class TenantContext
{
    protected static ?int $companyId = null;

    public static function set(?int $companyId): void
    {
        static::$companyId = $companyId;
    }

    public static function id(): ?int
    {
        return static::$companyId;
    }

    public static function clear(): void
    {
        static::$companyId = null;
    }
}
