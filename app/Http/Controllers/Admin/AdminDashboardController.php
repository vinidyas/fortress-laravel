<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $usersTotal = User::query()->count();
        $usersActive = User::query()->where('ativo', true)->count();
        $rolesTotal = Role::query()->count();
        $permissionsTotal = Permission::query()->count();

        $recentUsers = User::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->with('roles')
            ->get();

        $inactiveUsers = User::query()->where('ativo', false)->count();

        $data = [
            'counters' => [
                'users_total' => $usersTotal,
                'users_active' => $usersActive,
                'users_inactive' => $inactiveUsers,
                'roles_total' => $rolesTotal,
                'permissions_total' => $permissionsTotal,
            ],
            'recent_users' => UserResource::collection($recentUsers),
        ];

        return response()->json($data);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->hasPermission('admin.access'), 403);
    }
}
