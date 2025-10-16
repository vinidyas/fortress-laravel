<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        /** @var User|null $user */
        $user = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if ($user && ! $user->routeNotificationForMail()) {
            return back()->withErrors([
                'username' => 'Usuário encontrado, mas sem e-mail configurado. Procure o suporte para atualizar os dados.',
            ])->onlyInput('username');
        }

        $status = Password::broker()->sendResetLink([
            'username' => $credentials['username'],
        ]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['username' => __($status)])->onlyInput('username');
    }
}
