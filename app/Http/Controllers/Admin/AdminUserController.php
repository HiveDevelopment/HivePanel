<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with('roles:id,name')
                ->withCount('cells')
                ->latest()
                ->get()
                ->map(fn (User $user) => $this->userPayload($user)),
        ]);
    }

    public function show(User $user)
    {
        $user->load('roles:id,name')->loadCount('cells');

        return Inertia::render('Admin/Users/Show', [
            'user' => $this->userPayload($user),
            'cells' => Cell::query()
                ->where('owner_id', $user->id)
                ->with(['node:id,name,location', 'allocation:id,cell_id,ip,port,alias'])
                ->latest()
                ->get()
                ->map(fn (Cell $cell) => [
                    'id' => $cell->getRouteKey(),
                    'name' => $cell->name,
                    'comb' => $cell->comb,
                    'daemon_id' => $cell->daemon_id,
                    'node' => $cell->node,
                    'allocation' => $cell->allocation,
                    'created_at' => $cell->created_at?->toISOString(),
                ]),
        ]);
    }

    public function edit(User $user)
    {
        $user->load('roles:id,name');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->userPayload($user),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['required', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['uuid', 'exists:roles,id'],
        ]);

        if ($user->is_admin && ! $data['is_admin'] && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['is_admin' => 'You cannot remove super administrator access from the last super administrator.']);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => $data['is_admin'],
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        $audit->log(
            AuditEvent::USER_UPDATED,
            null,
            "User \"{$user->email}\" was updated.",
            ['user_id' => $user->id, 'roles' => $data['roles'] ?? [], 'is_admin' => $data['is_admin']]
        );

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated.');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getRouteKey(),
            'database_id' => $user->id,
            'is_admin' => $user->is_admin,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])->values()
                : [],
            'cells_count' => $user->cells_count ?? null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
