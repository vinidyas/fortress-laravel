<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $abilities = [];
        if ($user) {
            if (method_exists($user, 'getAllPermissions')) {
                $abilities = $user->getAllPermissions()->pluck('name')->all();
            }

            if (empty($abilities)) {
                $abilities = (array) ($user->permissoes ?? []);
            }
        }

        $userData = $user?->only(['id', 'username', 'nome', 'email', 'ativo']);
        if ($userData) {
            $userData['avatar_url'] = $user->avatar_url ?? null;
        }

        $portalTenant = null;
        if ($user && method_exists($user, 'hasTenantAccess') && $user->hasTenantAccess()) {
            $portalTenant = [
                'id' => $user->pessoa?->id,
                'nome' => $user->pessoa?->nome_razao_social,
                'cpf_cnpj' => $user->pessoa?->cpf_cnpj,
                'email' => $user->pessoa?->email,
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData,
                'abilities' => $abilities,
            ],
            'portal' => [
                'isPortalDomain' => $request->getHost() === config('app.portal_domain'),
                'domain' => config('app.portal_domain'),
            ],
            'portalTenant' => $portalTenant,
            'csrf_token' => csrf_token(),
            'status' => fn () => $request->session()->get('status'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
