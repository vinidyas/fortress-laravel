<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTenantUserRequest;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PortalTenantUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.access'), Response::HTTP_FORBIDDEN);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = Pessoa::query()
            ->whereJsonContains('papeis', 'Locatario')
            ->with(['usuarioPortal'])
            ->orderBy('nome_razao_social');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nome_razao_social', 'like', "%{$search}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%");
            });
        }

        $paginado = $query->paginate($perPage)->appends($request->query());

        $data = $paginado->getCollection()->map(function (Pessoa $pessoa) {
            $portalUser = $pessoa->usuarioPortal;

            return [
                'id' => $pessoa->id,
                'nome' => $pessoa->nome_razao_social,
                'email' => $pessoa->email,
                'telefone' => $pessoa->telefone,
                'cpf_cnpj' => $pessoa->cpf_cnpj,
                'has_portal_access' => $portalUser !== null,
                'portal_user' => $portalUser ? [
                    'id' => $portalUser->id,
                    'email' => $portalUser->email,
                    'username' => $portalUser->username,
                    'last_login_at' => optional($portalUser->last_login_at)->toDateTimeString(),
                ] : null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'per_page' => $paginado->perPage(),
                'last_page' => $paginado->lastPage(),
                'total' => $paginado->total(),
            ],
        ]);
    }

    public function store(CreateTenantUserRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $pessoa = Pessoa::query()->findOrFail($attributes['pessoa_id']);

        if (! $pessoa->hasPapel('Locatario')) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Pessoa informada não está marcada como locatário.');
        }

        $email = $attributes['email'];
        $username = $attributes['username'] ?? $email;

        $user = User::query()->firstOrNew([
            'pessoa_id' => $pessoa->id,
        ]);

        $emailConflict = User::query()
            ->where('email', $email)
            ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        if ($emailConflict) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'E-mail informado já está vinculado a outro usuário.');
        }

        $usernameConflict = User::query()
            ->where('username', $username)
            ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        if ($usernameConflict) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Username informado já está vinculado a outro usuário.');
        }

        $user->fill([
            'username' => $username,
            'email' => $email,
            'nome' => $pessoa->nome_razao_social,
            'ativo' => true,
            'password' => Str::random(40),
        ]);

        $permissions = Arr::wrap($user->permissoes);
        if (! in_array('portal.access', $permissions, true)) {
            $permissions[] = 'portal.access';
        }
        $user->permissoes = array_values($permissions);

        $user->save();

        Password::broker()->sendResetLink([
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Usuário do portal criado/atualizado com sucesso. E-mail de redefinição de senha enviado.',
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'pessoa_id' => $user->pessoa_id,
            ],
        ], Response::HTTP_CREATED);
    }
}
