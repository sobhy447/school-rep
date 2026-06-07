<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** تسجيل الدخول: يُرجِع Token + بيانات المستخدم. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['الحساب معطّل'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول',
            'data' => [
                'token' => $token,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /** بيانات المستخدم الحالي + صلاحياته. */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $this->userPayload($request->user()),
        ]);
    }

    /** تسجيل الخروج: إلغاء التوكن الحالي. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج',
            'data' => null,
        ]);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('role', 'company');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'name_en' => $user->name_en,
            'email' => $user->email,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'currency_code' => $user->company->currency_code,
            ] : null,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'slug' => $user->role->slug,
                'name' => $user->role->name,
            ] : null,
            'permissions' => $user->role
                ? $user->role->permissions()->pluck('key')->all()
                : [],
        ];
    }
}
