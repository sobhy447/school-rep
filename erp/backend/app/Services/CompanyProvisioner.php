<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * تجهيز شركة جديدة (مستأجر): إنشاء الشركة + الأدوار النظامية + مدير + شجرة حسابات + خرائط الترحيل.
 */
class CompanyProvisioner
{
    private const CHART = [
        ['1', 'الأصول', 'ASSET', null], ['11', 'الأصول المتداولة', 'ASSET', '1'],
        ['1101', 'الصندوق', 'ASSET', '11'], ['1102', 'البنك', 'ASSET', '11'],
        ['1103', 'العملاء', 'ASSET', '11'], ['1104', 'عهد الموظفين', 'ASSET', '11'],
        ['1105', 'المخزون', 'ASSET', '11'], ['1106', 'ضريبة المدخلات', 'ASSET', '11'],
        ['1107', 'شيكات تحت التحصيل', 'ASSET', '11'],
        ['2', 'الخصوم', 'LIABILITY', null], ['21', 'الموردون', 'LIABILITY', '2'],
        ['22', 'ضريبة المخرجات المستحقة', 'LIABILITY', '2'], ['23', 'الرواتب المستحقة', 'LIABILITY', '2'],
        ['24', 'الاستقطاعات المستحقة', 'LIABILITY', '2'], ['27', 'شيكات الدفع', 'LIABILITY', '2'],
        ['3', 'حقوق الملكية', 'EQUITY', null], ['31', 'رأس المال', 'EQUITY', '3'],
        ['32', 'الأرباح المحتجزة', 'EQUITY', '3'], ['39', 'حساب النتيجة', 'EQUITY', '3'],
        ['4', 'الإيرادات', 'REVENUE', null], ['41', 'إيرادات', 'REVENUE', '4'], ['42', 'أرباح/خسائر بيع أصول', 'REVENUE', '4'],
        ['5', 'المصروفات', 'EXPENSE', null], ['51', 'مصروفات إدارية', 'EXPENSE', '5'],
        ['52', 'مصروف الإهلاك', 'EXPENSE', '5'], ['53', 'تكلفة البضاعة المباعة', 'EXPENSE', '5'], ['54', 'مصروف الرواتب', 'EXPENSE', '5'],
    ];

    public function provision(string $code, string $name, string $adminName, string $adminEmail, string $adminPassword): Company
    {
        return DB::transaction(function () use ($code, $name, $adminName, $adminEmail, $adminPassword) {
            $company = Company::query()->create(['code' => $code, 'name' => $name, 'currency_code' => 'KWD', 'is_active' => true]);

            $allKeys = Permission::pluck('key')->all();
            $defs = ['system_admin' => 'مدير النظام', 'financial_manager' => 'مدير مالي', 'accountant' => 'محاسب', 'cashier' => 'كاشير'];
            $adminRole = null;
            foreach ($defs as $slug => $rn) {
                $role = Role::query()->create(['company_id' => $company->id, 'slug' => $slug, 'name' => $rn, 'is_system' => true]);
                $role->syncPermissionKeys($slug === 'cashier' ? ['vouchers.view', 'vouchers.create'] : $allKeys);
                if ($slug === 'system_admin') $adminRole = $role;
            }

            $admin = User::query()->create([
                'company_id' => $company->id, 'role_id' => $adminRole->id, 'name' => $adminName,
                'email' => $adminEmail, 'password' => Hash::make($adminPassword), 'is_active' => true,
            ]);

            $idByCode = [];
            foreach (self::CHART as [$c, $nm, $type, $parent]) {
                $pid = $parent ? ($idByCode[$parent] ?? null) : null;
                $acc = Account::query()->create(['company_id' => $company->id, 'code' => $c, 'name' => $nm, 'type' => $type, 'parent_id' => $pid]);
                $idByCode[$c] = $acc->id;
                if ($pid) Account::query()->withoutGlobalScopes()->whereKey($pid)->update(['accepts_entries' => false]);
            }
            Account::query()->withoutGlobalScopes()->where('company_id', $company->id)->whereIn('code', ['1101', '1102'])->update(['is_cash_or_bank' => true]);
            Account::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1103')->update(['party_type' => 'CUSTOMER']);
            Account::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('code', '21')->update(['party_type' => 'VENDOR']);

            $map = ['petty_cash_account_id' => '1104', 'income_summary_account_id' => '39', 'retained_earnings_account_id' => '32',
                'vat_input_account_id' => '1106', 'vat_output_account_id' => '22', 'salary_expense_account_id' => '54',
                'salaries_payable_account_id' => '23', 'deductions_payable_account_id' => '24',
                'cheques_collection_account_id' => '1107', 'cheques_payable_account_id' => '27'];
            foreach ($map as $key => $c) {
                CompanySetting::put($company->id, $key, (string) ($idByCode[$c] ?? ''));
            }

            // سنة مالية افتراضية
            \App\Models\FiscalYear::query()->create(['company_id' => $company->id, 'name' => date('Y'),
                'start_date' => date('Y') . '-01-01', 'end_date' => date('Y') . '-12-31', 'status' => 'OPEN']);

            return $company;
        });
    }
}
