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
        if ($user && $user->company_id) {
            TenantContext::set((int) $user->company_id);
        }

        return $next($request);
    }
}
