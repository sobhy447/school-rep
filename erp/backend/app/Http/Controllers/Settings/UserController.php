<?php

namespace App\Http\Controllers\Settings;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends BaseCrudController
{
    protected string $modelClass = User::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:6'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('company_id', TenantContext::id())],
            'is_active' => ['boolean'],
        ];
    }

    public function index(): JsonResponse
    {
        return $this->ok(User::query()->where('company_id', TenantContext::id())->with('role:id,name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'role_id' => $u->role_id, 'role' => $u->role?->name, 'is_active' => $u->is_active]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $data['company_id'] = TenantContext::id();
        $data['password'] = Hash::make($data['password']);
        $user = User::query()->create($data);
        return $this->ok(['id' => $user->id], 'تم إنشاء المستخدم', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()->where('company_id', TenantContext::id())->findOrFail($id);
        $data = $request->validate($this->rules($request, $id));
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        return $this->ok(['id' => $user->id], 'تم تحديث المستخدم');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::query()->where('company_id', TenantContext::id())->findOrFail($id);
        if ($user->id === auth()->id()) {
            return $this->ok(null, 'لا يمكنك حذف حسابك الحالي', 422);
        }
        $user->delete();
        return $this->ok(null, 'تم حذف المستخدم');
    }
}
