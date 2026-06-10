<?php

namespace App\Http\Controllers\Settings;

use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends BaseCrudController
{
    protected string $modelClass = Role::class;

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50',
                Rule::unique('roles', 'slug')->where('company_id', TenantContext::id())->ignore($id)],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }

    public function index(): JsonResponse
    {
        return $this->ok(Role::query()->where('company_id', TenantContext::id())->withCount('permissions')->get());
    }

    public function show(int $id): JsonResponse
    {
        $role = Role::query()->where('company_id', TenantContext::id())->with('permissions:id,key')->findOrFail($id);
        return $this->ok(['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug,
            'permissions' => $role->permissions->pluck('key')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $role = Role::query()->create(['company_id' => TenantContext::id(), 'name' => $data['name'], 'slug' => $data['slug']]);
        $role->syncPermissionKeys($data['permissions'] ?? []);
        return $this->ok(['id' => $role->id], 'تم إنشاء الدور', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::query()->where('company_id', TenantContext::id())->findOrFail($id);
        $data = $request->validate($this->rules($request, $id));
        $role->update(['name' => $data['name'], 'slug' => $data['slug']]);
        $role->syncPermissionKeys($data['permissions'] ?? []);
        return $this->ok(['id' => $role->id], 'تم تحديث الدور وصلاحياته');
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::query()->where('company_id', TenantContext::id())->findOrFail($id);
        if ($role->users()->exists()) {
            return $this->ok(null, 'لا يمكن حذف دور مرتبط بمستخدمين', 422);
        }
        $role->delete();
        return $this->ok(null, 'تم حذف الدور');
    }

    /** كل الصلاحيات مجمّعة حسب الموديول (لمحرّر الأدوار). */
    public function permissions(): JsonResponse
    {
        $grouped = \App\Models\Permission::query()->orderBy('module')->get()
            ->groupBy('module')->map(fn ($items, $module) => [
                'module' => $module,
                'items' => $items->map(fn ($p) => ['key' => $p->key, 'label' => $p->label_ar])->values(),
            ])->values();
        return $this->ok($grouped);
    }
}
