<?php

namespace Database\Seeders;

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
