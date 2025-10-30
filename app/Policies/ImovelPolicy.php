<?php

namespace App\Policies;

use App\Models\Imovel;
use App\Models\User;

class ImovelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkAbility($user, 'imoveis.view');
    }

    public function view(User $user, Imovel $imovel): bool
    {
        return $this->checkAbility($user, 'imoveis.view');
    }

    public function create(User $user): bool
    {
        return $this->checkAbility($user, 'imoveis.create');
    }

    public function update(User $user, Imovel $imovel): bool
    {
        return $this->checkAbility($user, 'imoveis.update');
    }

    public function delete(User $user, Imovel $imovel): bool
    {
        return $this->checkAbility($user, 'imoveis.delete');
    }

    private function checkAbility(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
