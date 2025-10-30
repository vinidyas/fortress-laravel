<?php

namespace App\Policies;

use App\Models\Pessoa;
use App\Models\User;

class PessoaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkAbility($user, 'pessoas.view');
    }

    public function view(User $user, Pessoa $pessoa): bool
    {
        return $this->checkAbility($user, 'pessoas.view');
    }

    public function create(User $user): bool
    {
        return $this->checkAbility($user, 'pessoas.create');
    }

    public function update(User $user, Pessoa $pessoa): bool
    {
        return $this->checkAbility($user, 'pessoas.update');
    }

    public function delete(User $user, Pessoa $pessoa): bool
    {
        return $this->checkAbility($user, 'pessoas.delete');
    }

    private function checkAbility(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
