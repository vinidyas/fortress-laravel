<?php

namespace App\Policies;

use App\Models\Contrato;
use App\Models\User;

class ContratoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkAbility($user, 'contratos.view');
    }

    public function view(User $user, Contrato $contrato): bool
    {
        return $this->checkAbility($user, 'contratos.view');
    }

    public function create(User $user): bool
    {
        return $this->checkAbility($user, 'contratos.create');
    }

    public function update(User $user, Contrato $contrato): bool
    {
        return $this->checkAbility($user, 'contratos.update');
    }

    public function delete(User $user, Contrato $contrato): bool
    {
        return $this->checkAbility($user, 'contratos.delete');
    }

    private function checkAbility(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
