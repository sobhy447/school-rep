<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
    }

    private function withoutActions(array $keys, array $actions): array
    {
        return array_values(array_filter($keys, function ($key) use ($actions) {
            $action = substr($key, strrpos($key, '.') + 1);
            return ! in_array($action, $actions, true);
        }));
    }
}
