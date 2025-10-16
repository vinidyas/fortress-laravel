<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminDashboardPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('admin.access'), 403);

        $usersTotal = User::query()->count();
        $usersActive = User::query()->where('ativo', true)->count();
        $inactiveUsers = User::query()->where('ativo', false)->count();
        $rolesTotal = Role::query()->count();
        $permissionsTotal = Permission::query()->count();

        $recentUsers = User::query()
            ->orderByDesc('created_at')
            ->limit(8)
            ->with('roles')
            ->get()
            ->map(fn ($recent) => [
                'id' => $recent->id,
                'nome' => $recent->nome,
                'username' => $recent->username,
                'ativo' => (bool) $recent->ativo,
                'roles' => $recent->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug ?? null,
                ]),
                'created_at' => optional($recent->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users_total' => $usersTotal,
                'users_active' => $usersActive,
                'users_inactive' => $inactiveUsers,
                'roles_total' => $rolesTotal,
                'permissions_total' => $permissionsTotal,
            ],
            'recent_users' => $recentUsers,
        ]);
    }
}
