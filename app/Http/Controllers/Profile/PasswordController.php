<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Profile/Password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! Hash::check($request->validated()['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'A senha atual não confere.',
            ]);
        }

        $user->forceFill([
            'password' => $request->validated()['password'],
        ])->save();

        $request->session()->put('password_hash_at', time());

        return back()->with('status', 'Senha atualizada com sucesso.');
    }
}
