<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CellPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminCellUserController extends Controller
{
    public function index(Cell $cell)
    {
        return Inertia::render('Admin/Cells/Users', ['cell' => $cell->only('id', 'name', 'owner_id'), 'owner' => $cell->owner?->only('name', 'email'), 'accounts' => $cell->subUsers()->with('user:id,name,email')->get()->map(fn ($row) => ['id' => $row->user_id, 'name' => $row->user->name, 'email' => $row->user->email, 'permissions' => $row->permissions ?? []]), 'permissionGroups' => CellPermissions::groups()]);
    }

    public function store(Cell $cell, Request $request, AuditLogger $audit)
    {
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email'], 'permissions' => ['present', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in(CellPermissions::all())]]);
        $user = User::where('email', $data['email'])->firstOrFail();
        DB::transaction(function () use ($cell, $user, $data) {
            $current = Cell::whereKey($cell->id)->lockForUpdate()->firstOrFail();
            if ((string) $current->owner_id === (string) $user->id) {
                throw ValidationException::withMessages(['email' => __('The owner already has full access.')]);
            }$current->subUsers()->updateOrCreate(['user_id' => $user->id], ['permissions' => $data['permissions']]);
        });
        $audit->log(AuditEvent::SETTINGS_UPDATED, $cell, 'Administrator updated Cell account access.', ['user_id' => $user->id]);

        return back()->with('success', __('Account access saved.'));
    }

    public function destroy(Cell $cell, User $user, AuditLogger $audit)
    {
        $row = $cell->subUsers()->where('user_id', $user->id)->firstOrFail();
        $row->delete();
        $audit->log(AuditEvent::SETTINGS_UPDATED, $cell, 'Administrator removed Cell account access.', ['user_id' => $user->id]);

        return back()->with('success', __('Account access removed.'));
    }
}
