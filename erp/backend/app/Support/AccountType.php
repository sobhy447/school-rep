<?php

namespace App\Support;

/**
 * قواعد أنواع الحسابات حسب المعايير المحاسبية العالمية.
 * - الطبيعة (Normal Balance): الأصول والمصروفات مدينة؛ الخصوم وحقوق الملكية والإيرادات دائنة.
 * - القائمة المالية: الأصول/الخصوم/الملكية ➜ الميزانية؛ الإيرادات/المصروفات ➜ قائمة الدخل.
 */
class AccountType
{
    public const ASSET = 'ASSET';
    public const LIABILITY = 'LIABILITY';
    public const EQUITY = 'EQUITY';
    public const REVENUE = 'REVENUE';
    public const EXPENSE = 'EXPENSE';

    public const ALL = [self::ASSET, self::LIABILITY, self::EQUITY, self::REVENUE, self::EXPENSE];

    public const LABELS_AR = [
        self::ASSET => 'أصول',
        self::LIABILITY => 'خصوم',
        self::EQUITY => 'حقوق ملكية',
        self::REVENUE => 'إيرادات',
        self::EXPENSE => 'مصروفات',
    ];

    /** الطبيعة المدينة/الدائنة للحساب. */
    public static function normalBalance(string $type): string
    {
        return in_array($type, [self::ASSET, self::EXPENSE], true) ? 'DEBIT' : 'CREDIT';
    }

    /** القائمة المالية التي يظهر فيها الحساب. */
    public static function statement(string $type): string
    {
        return in_array($type, [self::REVENUE, self::EXPENSE], true)
            ? 'INCOME_STATEMENT'
            : 'BALANCE_SHEET';
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }

    /** هل المبلغ يزيد الرصيد لهذا النوع؟ (مدين يزيد الأصول/المصروفات، دائن يزيد الباقي) */
    public static function signedBalance(string $type, float $debit, float $credit): float
    {
        return self::normalBalance($type) === 'DEBIT'
            ? round($debit - $credit, 3)
            : round($credit - $debit, 3);
    }
}
