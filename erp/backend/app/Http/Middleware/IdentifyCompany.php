<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يضبط شركة المستخدم الحالي في TenantContext بعد المصادقة.
 * يجب أن يأتي بعد auth:sanctum في سلسلة الـ middleware.
 */
class IdentifyCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            // المستخدم الخارق يمكنه العمل ضمن شركة نشطة مختارة؛ غيره مقيّد بشركته.
            $companyId = ($user->is_super && $user->active_company_id) ? (int) $user->active_company_id : (int) $user->company_id;
            if ($companyId) {
                TenantContext::set($companyId);
            }
        }

        return $next($request);
    }
}
