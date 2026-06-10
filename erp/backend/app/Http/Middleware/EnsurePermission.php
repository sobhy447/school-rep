<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يفرض امتلاك المستخدم صلاحية محددة. الاستخدام: ->middleware('permission:accounts.create')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لهذا الإجراء',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
