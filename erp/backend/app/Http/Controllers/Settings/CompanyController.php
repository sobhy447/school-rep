<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyProvisioner;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    /** الشركة الحالية (النشطة). */
    public function current(): JsonResponse
    {
        return $this->ok(Company::query()->findOrFail(TenantContext::id()));
    }

    public function update(Request $request): JsonResponse
    {
        $company = Company::query()->findOrFail(TenantContext::id());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);
        $company->update($data);
        return $this->ok($company, 'تم تحديث بيانات الشركة');
    }

    /** قائمة الشركات (للمستخدم الخارق فقط). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companies = $user->is_super
            ? Company::query()->withoutGlobalScopes()->orderBy('name')->get()
            : Company::query()->where('id', $user->company_id)->get();
        return $this->ok($companies);
    }

    /** إنشاء شركة جديدة (مستأجر) — للمستخدم الخارق. */
    public function store(Request $request, CompanyProvisioner $provisioner): JsonResponse
    {
        $this->assertSuper($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:6'],
        ]);
        $company = $provisioner->provision($data['code'], $data['name'], $data['admin_name'], $data['admin_email'], $data['admin_password']);
        return $this->ok($company, 'تم إنشاء الشركة وتجهيزها', 201);
    }

    /** التبديل إلى شركة أخرى (للمستخدم الخارق). */
    public function switch(Request $request, int $id): JsonResponse
    {
        $this->assertSuper($request);
        Company::query()->withoutGlobalScopes()->findOrFail($id);
        $request->user()->update(['active_company_id' => $id]);
        return $this->ok(['active_company_id' => $id], 'تم التبديل للشركة');
    }

    private function assertSuper(Request $request): void
    {
        if (! $request->user()->is_super) {
            throw ValidationException::withMessages(['company' => ['هذه العملية للمستخدم الخارق فقط']]);
        }
    }
}
