<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Services\AvatarManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AvatarManager $avatarManager,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $query = User::query()->with(['roles', 'permissions']);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $query->where('ativo', true);
        } elseif ($status === 'inactive') {
            $query->where('ativo', false);
        }

        $roleFilter = $request->query('role');
        if ($roleFilter) {
            $query->where(function ($q) use ($roleFilter) {
                $q->where('role_id', $roleFilter)
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('id', $roleFilter)->orWhere('slug', $roleFilter));
            });
        }

        $query->orderBy('nome');

        $users = $query->paginate($perPage)->appends($request->query());

        return UserResource::collection($users)->additional([
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ])->response();
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $roles = collect($validated['roles'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $permissions = collect($validated['permissions'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($validated['roles'], $validated['permissions'], $validated['avatar'], $validated['send_password_reset']);

        $validated['role_id'] = $validated['role_id'] ?? $roles->first() ?? null;
        $validated['permissoes'] = $permissions;

        /** @var User $user */
        $user = DB::transaction(function () use ($validated, $roles, $permissions, $request) {
            /** @var User $user */
            $user = User::query()->create($validated);

            if ($roles->isNotEmpty()) {
                $user->syncRoles(Role::query()->whereIn('id', $roles)->get());
            } elseif ($validated['role_id']) {
                $user->syncRoles([$validated['role_id']]);
            } else {
                $user->syncRoles([]);
            }

            $user->syncPermissions($permissions);

            if ($request->hasFile('avatar')) {
                $this->avatarManager->storeForUser($user, $request->file('avatar'));
            }

            return $user;
        });

        if ($request->boolean('send_password_reset') && $user->routeNotificationForMail()) {
            Password::broker()->sendResetLink(['username' => $user->username]);
        }

        return (new UserResource($user->fresh(['roles', 'permissions'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $roles = collect($validated['roles'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $permissions = collect($validated['permissions'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($validated['roles'], $validated['permissions'], $validated['avatar'], $validated['remove_avatar'], $validated['send_password_reset']);

        if (array_key_exists('password', $validated) && blank($validated['password'])) {
            unset($validated['password']);
        }

        if ($user->id === $request->user()->id && array_key_exists('ativo', $validated) && ! $validated['ativo']) {
            throw ValidationException::withMessages([
                'ativo' => 'Você não pode desativar o próprio usuário.',
            ]);
        }

        $validated['permissoes'] = $permissions;
        $validated['role_id'] = $validated['role_id'] ?? $roles->first() ?? $user->role_id;

        DB::transaction(function () use ($user, $validated, $roles, $permissions, $request) {
            $user->update(Arr::except($validated, ['ativo']));

            if (array_key_exists('ativo', $validated) && $validated['ativo'] !== null) {
                $user->ativo = (bool) $validated['ativo'];
                $user->save();
            }

            if ($roles->isNotEmpty()) {
                $user->syncRoles(Role::query()->whereIn('id', $roles)->get());
            } elseif ($validated['role_id']) {
                $user->syncRoles([$validated['role_id']]);
            }

            $user->syncPermissions($permissions);

            if ($request->boolean('remove_avatar')) {
                $this->avatarManager->removeForUser($user);
            } elseif ($request->hasFile('avatar')) {
                $this->avatarManager->storeForUser($user, $request->file('avatar'));
            }
        });

        if ($request->boolean('send_password_reset') && $user->routeNotificationForMail()) {
            Password::broker()->sendResetLink(['username' => $user->username]);
        }

        return (new UserResource($user->fresh(['roles', 'permissions'])))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $this->authorizeAccess($request);

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'Você não pode alterar o status do próprio usuário.',
            ]);
        }

        $user->ativo = ! $user->ativo;
        $user->save();

        return (new UserResource($user->fresh(['roles', 'permissions'])))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function sendResetLink(Request $request, User $user): JsonResponse
    {
        $this->authorizeAccess($request);

        if (! $user->routeNotificationForMail()) {
            throw ValidationException::withMessages([
                'user' => 'Não foi possível enviar o link pois o usuário não possui e-mail configurado.',
            ]);
        }

        $status = Password::broker()->sendResetLink(['username' => $user->username]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'user' => __($status),
            ]);
        }

        return response()->json([
            'message' => 'Link de redefinição enviado.',
        ], Response::HTTP_OK);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('admin.access'), 403);
    }
}
