<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminRoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            'permissionGroups' => AdminPermissions::groups(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRole($request);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permissions']);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        $role->load('permissions:id,name');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions->pluck('name')->values(),
            ],
            'permissionGroups' => AdminPermissions::groups(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validateRole($request);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permissions']);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 422, 'System roles cannot be deleted.');

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    private function validateRole(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::all())],
        ]);
    }

    private function syncPermissions(Role $role, array $permissions): void
    {
        $ids = Permission::query()
            ->whereIn('name', $permissions)
            ->pluck('id');

        $role->permissions()->sync($ids);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $counter = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
