<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('admin.access'), 403);

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug ?? null,
                'description' => $role->description,
                'is_system' => (bool) ($role->is_system ?? false),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($permission) => [
                'name' => $permission->name,
                'description' => $permission->description,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'available_roles' => $roles,
            'available_permissions' => $permissions,
        ]);
    }
}
