<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\VoucherType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedDemoCompany('NOOR', 'مكتب النور للمحاماة', 'admin@noor.test');
        $this->seedDemoCompany('AMAL', 'شركة الأمل التجارية', 'admin@amal.test');
    }

    /** الصلاحيات المرجعية لوحدات المرحلة 0. */
    private function seedPermissions(): void
    {
        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'post', 'unpost', 'lock', 'print', 'export'];
        $modules = [
            'settings' => ['الإعدادات', 'Settings'],
            'accounts' => ['دليل الحسابات', 'Chart of Accounts'],
            'journals' => ['القيود اليومية', 'Journal Entries'],
            'vouchers' => ['السندات', 'Vouchers'],
            'customers' => ['العملاء والموردون', 'Customers & Vendors'],
            'petty_cash' => ['العهد', 'Petty Cash'],
            'settlements' => ['الأمانات والسداد', 'Trust & Settlements'],
            'reports' => ['التقارير', 'Reports'],
            'fixed_assets' => ['الأصول الثابتة', 'Fixed Assets'],
            'banks' => ['البنوك والتسويات', 'Banks & Reconciliation'],
            'inventory' => ['المخزون', 'Inventory'],
        ];

        foreach ($modules as $module => [$ar, $en]) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['key' => "$module.$action"],
                    [
                        'module' => $module,
                        'label_ar' => "$ar — $action",
                        'label_en' => "$en — $action",
                    ]
                );
            }
        }
    }

    /** شركة تجريبية بأدوارها النظامية ومستخدم مدير نظام. */
    private function seedDemoCompany(string $code, string $name, string $adminEmail): void
    {
        $company = Company::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'currency_code' => 'KWD', 'is_active' => true]
        );

        $allKeys = Permission::pluck('key')->all();

        $roleDefs = [
            'system_admin' => ['مدير النظام', $allKeys],
            'financial_manager' => ['مدير مالي', $allKeys],
            'accountant' => ['محاسب', $this->withoutActions($allKeys, ['delete', 'lock'])],
            'cashier' => ['كاشير', ['vouchers.view', 'vouchers.create', 'vouchers.print', 'customers.view']],
        ];

        $roles = [];
        foreach ($roleDefs as $slug => [$roleName, $keys]) {
            $role = Role::firstOrCreate(
                ['company_id' => $company->id, 'slug' => $slug],
                ['name' => $roleName, 'is_system' => true]
            );
            $role->syncPermissionKeys($keys);
            $roles[$slug] = $role;
        }

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'company_id' => $company->id,
                'role_id' => $roles['system_admin']->id,
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->seedSettings($company->id);
        $this->seedAccounting($company->id);
    }

    /** فئات الحسابات + شجرة حسابات نموذجية لكل شركة. */
    private function seedAccounting(int $companyId): void
    {
        // فئات (تعدد المجموعات): نوع العميل + التابع له
        foreach ([
            ['customer_type', 'VIP', 'عميل مميّز'],
            ['customer_type', 'NORMAL', 'عميل عادي'],
            ['affiliation', 'BRANCH_A', 'تابع للفرع أ'],
        ] as [$group, $code, $name]) {
            AccountCategory::firstOrCreate(
                ['company_id' => $companyId, 'group' => $group, 'code' => $code],
                ['name' => $name, 'applies_to_type' => 'ASSET']
            );
        }

        // شجرة حسابات نموذجية: [code, name, type, parent_code]
        $chart = [
            ['1', 'الأصول', 'ASSET', null],
            ['11', 'الأصول المتداولة', 'ASSET', '1'],
            ['1101', 'الصندوق', 'ASSET', '11'],
            ['1102', 'البنك', 'ASSET', '11'],
            ['1103', 'العملاء', 'ASSET', '11'],
            ['1104', 'عهد الموظفين', 'ASSET', '11'],
            ['1105', 'المخزون', 'ASSET', '11'],
            ['12', 'الأصول الثابتة', 'ASSET', '1'],
            ['1201', 'سيارات', 'ASSET', '12'],
            ['1202', 'مجمع إهلاك السيارات', 'ASSET', '12'],
            ['2', 'الخصوم', 'LIABILITY', null],
            ['21', 'الموردون', 'LIABILITY', '2'],
            ['3', 'حقوق الملكية', 'EQUITY', null],
            ['31', 'رأس المال', 'EQUITY', '3'],
            ['32', 'الأرباح المحتجزة', 'EQUITY', '3'],
            ['39', 'حساب النتيجة', 'EQUITY', '3'],
            ['4', 'الإيرادات', 'REVENUE', null],
            ['41', 'إيرادات الأتعاب', 'REVENUE', '4'],
            ['42', 'أرباح/خسائر بيع أصول', 'REVENUE', '4'],
            ['5', 'المصروفات', 'EXPENSE', null],
            ['51', 'مصروفات إدارية', 'EXPENSE', '5'],
            ['52', 'مصروف الإهلاك', 'EXPENSE', '5'],
            ['53', 'تكلفة البضاعة المباعة', 'EXPENSE', '5'],
        ];

        $idByCode = [];
        foreach ($chart as [$code, $name, $type, $parentCode]) {
            $parentId = $parentCode ? ($idByCode[$parentCode] ?? null) : null;
            $acc = Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'parent_id' => $parentId]
            );
            $idByCode[$code] = $acc->id;
            // الأب يصبح تصنيفاً (لا يقبل حركة)
            if ($parentId) {
                Account::query()->withoutGlobalScopes()->whereKey($parentId)->update(['accepts_entries' => false]);
            }
        }

        // تعليم حسابات النقدية/البنك (للتحقق في السندات)
        Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->whereIn('code', ['1101', '1102'])
            ->update(['is_cash_or_bank' => true]);

        // تعليم حساب العملاء كـ «عميل» (الأطراف حسابات في الشجرة)
        Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('code', '1103')
            ->update(['party_type' => 'CUSTOMER']);
        Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('code', '21')
            ->update(['party_type' => 'VENDOR']);

        // مركز تكلفة إضافي مربوط بحساب العملاء + اسم موكل/خصم (تدفّق مكاتب المحاماة)
        if (isset($idByCode['1103'])) {
            \App\Models\CostCenter::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'CASE-001'],
                [
                    'name' => 'قضية 001',
                    'linked_account_id' => $idByCode['1103'],
                    'client_name' => 'موكل تجريبي',
                    'counterparty_name' => 'خصم تجريبي',
                ]
            );
        }

        // إعدادات الشركة: حساب العهدة + النتيجة + الأرباح المحتجزة
        \App\Models\CompanySetting::put($companyId, 'petty_cash_account_id', (string) ($idByCode['1104'] ?? ''));
        \App\Models\CompanySetting::put($companyId, 'income_summary_account_id', (string) ($idByCode['39'] ?? ''));
        \App\Models\CompanySetting::put($companyId, 'retained_earnings_account_id', (string) ($idByCode['32'] ?? ''));

        // مخزون: مخزن + صنفان مرتبطان بالحسابات
        $wh = \App\Models\Warehouse::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'WH1'],
            ['name' => 'المخزن الرئيسي']
        );
        foreach ([['ITM-1', 'صنف أ', 10, 15], ['ITM-2', 'صنف ب', 20, 30]] as [$code, $name, $pp, $sp]) {
            \App\Models\InventoryItem::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => $name, 'purchase_price' => $pp, 'sale_price' => $sp,
                    'inventory_account_id' => $idByCode['1105'] ?? null,
                    'cogs_account_id' => $idByCode['53'] ?? null,
                    'revenue_account_id' => $idByCode['41'] ?? null,
                ]
            );
        }

        // بنود العهد (بحدود تكرار)
        foreach ([
            ['PRINT', 'طباعة', 5.000, 3, 1, false],
            ['TRANSPORT', 'مواصلات', null, null, null, false],
        ] as [$code, $name, $def, $maxR, $forb, $perm]) {
            \App\Models\PettyCashItem::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => $name, 'default_amount' => $def, 'max_repeat' => $maxR,
                    'forbidden_months' => $forb, 'is_permanent' => $perm,
                    'expense_account_id' => $idByCode['51'] ?? null,
                ]
            );
        }
    }

    /** بيانات إعدادات تجريبية لكل شركة. */
    private function seedSettings(int $companyId): void
    {
        Branch::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'MAIN'],
            ['name' => 'الفرع الرئيسي', 'name_en' => 'Main Branch', 'is_main' => true]
        );

        FiscalYear::firstOrCreate(
            ['company_id' => $companyId, 'name' => '2026'],
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']
        );

        Currency::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KWD'],
            ['name' => 'دينار كويتي', 'name_en' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'is_base' => true, 'exchange_rate' => 1]
        );
        Currency::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'USD'],
            ['name' => 'دولار أمريكي', 'name_en' => 'US Dollar', 'symbol' => '$', 'is_base' => false, 'exchange_rate' => 0.307]
        );

        foreach ([['ADMIN', 'الإدارة'], ['SALES', 'المبيعات']] as [$code, $name]) {
            CostCenter::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name]
            );
        }

        foreach ([
            ['REC', 'سند قبض', 'RECEIPT', 'Q'],
            ['PAY', 'سند صرف', 'PAYMENT', 'P'],
            ['TRF', 'سند تحويل', 'TRANSFER', 'T'],
        ] as [$code, $name, $dir, $prefix]) {
            VoucherType::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'direction' => $dir, 'prefix' => $prefix]
            );
        }

        TaxRate::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'VAT0'],
            ['name' => 'ضريبة القيمة المضافة', 'name_en' => 'VAT', 'rate' => 0, 'type' => 'VAT', 'is_enabled' => false]
        );
    }

    private function withoutActions(array $keys, array $actions): array
    {
        return array_values(array_filter($keys, function ($key) use ($actions) {
            $action = substr($key, strrpos($key, '.') + 1);
            return ! in_array($action, $actions, true);
        }));
    }
}
