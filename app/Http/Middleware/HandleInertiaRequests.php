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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData,
                'abilities' => $abilities,
            ],
            'csrf_token' => csrf_token(),
            'status' => fn () => $request->session()->get('status'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
