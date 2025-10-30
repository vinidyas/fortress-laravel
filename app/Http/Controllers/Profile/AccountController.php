<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAccountRequest;
use App\Services\AvatarManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AvatarManager $avatarManager,
    ) {
    }

    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        return Inertia::render('Profile/Account', [
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validated();

        $user->forceFill([
            'nome' => $validated['nome'],
            'email' => $validated['email'] ?? null,
        ])->save();

        if ($request->boolean('remove_avatar')) {
            $this->avatarManager->removeForUser($user);
        } elseif ($request->hasFile('avatar')) {
            $this->avatarManager->storeForUser($user, $request->file('avatar'));
        }

        return redirect()->route('profile.edit')->with('status', 'Perfil atualizado com sucesso.');
    }
}
