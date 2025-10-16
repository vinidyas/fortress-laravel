<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\Admin\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles)->additional([
            'permissions' => PermissionResource::collection(
                Permission::query()->orderBy('name')->get()
            ),
        ])->response();
    }

    public function store(RoleStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $permissions = $validated['permissions'] ?? [];
        unset($validated['permissions']);

        $role = DB::transaction(function () use ($validated, $permissions) {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'guard_name' => 'web',
                'is_system' => false,
            ]);

            $role->syncPermissions($permissions);

            return $role;
        });

        return (new RoleResource($role->fresh('permissions')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(RoleUpdateRequest $request, Role $role): JsonResponse
    {
        if ($role->is_system && $role->slug === 'admin') {
            // Ensure admin role always keeps admin.access
            $permissions = collect($request->validated()['permissions'] ?? [])
                ->push('admin.access')
                ->unique()
                ->values()
                ->all();
        } else {
            $permissions = $request->validated()['permissions'] ?? [];
        }

        $validated = $request->validated();
        unset($validated['permissions']);

        if ($role->is_system && $role->slug === 'admin' && isset($validated['slug']) && $validated['slug'] !== 'admin') {
            throw ValidationException::withMessages([
                'slug' => 'Não é possível alterar o identificador do papel de administrador.',
            ]);
        }

        $role->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! $role->is_system && isset($validated['slug'])) {
            $role->slug = $validated['slug'];
        }

        $role->save();

        if ($role->is_system && $role->slug === 'admin') {
            $role->syncPermissions($permissions);
        } else {
            $role->syncPermissions($permissions);
        }

        return (new RoleResource($role->fresh('permissions')))->response();
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorizeAccess($request);

        if ($role->is_system || in_array($role->slug, ['admin'], true)) {
            throw ValidationException::withMessages([
                'role' => 'Este papel é protegido e não pode ser removido.',
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Não é possível remover um papel associado a usuários.',
            ]);
        }

        $role->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('admin.access'), 403);
    }
}
