<?php

namespace App\Policies;

use App\Models\Condominio;
use App\Models\User;

class CondominioPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkAbility($user, 'condominios.view');
    }

    public function view(User $user, Condominio $condominio): bool
    {
        return $this->checkAbility($user, 'condominios.view');
    }

    public function create(User $user): bool
    {
        return $this->checkAbility($user, 'condominios.create');
    }

    public function update(User $user, Condominio $condominio): bool
    {
        return $this->checkAbility($user, 'condominios.update');
    }

    public function delete(User $user, Condominio $condominio): bool
    {
        return $this->checkAbility($user, 'condominios.delete');
    }

    private function checkAbility(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}

