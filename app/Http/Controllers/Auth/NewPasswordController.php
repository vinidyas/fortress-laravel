<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'username' => $request->string('username')->toString(),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $throttleKey = 'password-reset:'.$credentials['username'];

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'username' => __('passwords.throttled'),
            ]);
        }

        $status = Password::reset(
            [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'password_confirmation' => $credentials['password_confirmation'],
                'token' => $credentials['token'],
            ],
            function ($user) use ($credentials) {
                $user->forceFill([
                    'password' => $credentials['password'],
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['username' => __($status)])->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);

        return redirect()->route('login')->with('status', __($status));
    }
}
