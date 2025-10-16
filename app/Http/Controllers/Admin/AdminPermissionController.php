<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class AdminPermissionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('admin.access'), 403);

        $permissions = Permission::query()->orderBy('name')->get();

        return PermissionResource::collection($permissions)->response();
    }
}
