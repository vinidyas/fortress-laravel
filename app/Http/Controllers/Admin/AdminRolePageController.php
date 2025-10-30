<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class AdminRolePageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('admin.access'), 403);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($permission) => [
                'name' => $permission->name,
                'description' => $permission->description,
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'available_permissions' => $permissions,
        ]);
    }
}
