<?php

namespace App\Policies;

use App\Models\Fatura;
use App\Models\User;

class FaturaPolicy
{
    public function viewAny(User $user): bool
    {
        if (method_exists($user, 'hasTenantAccess') && $user->hasTenantAccess()) {
            return true;
        }

        return $this->checkAbility($user, 'faturas.view');
    }

    public function view(User $user, Fatura $fatura): bool
    {
        if (method_exists($user, 'hasTenantAccess') && $user->hasTenantAccess()) {
            $pessoaId = $user->pessoa_id;
            $locatarioId = $fatura->contrato?->locatario_id;

            if ($pessoaId && $locatarioId && (int) $pessoaId === (int) $locatarioId) {
                return true;
            }
        }

        return $this->checkAbility($user, 'faturas.view');
    }

    public function create(User $user): bool
    {
        return $this->checkAbility($user, 'faturas.create');
    }

    public function update(User $user, Fatura $fatura): bool
    {
        return $this->checkAbility($user, 'faturas.update');
    }

    public function delete(User $user, Fatura $fatura): bool
    {
        return $this->checkAbility($user, 'faturas.delete');
    }

    public function settle(User $user, Fatura $fatura): bool
    {
        return $this->checkAbility($user, 'faturas.settle');
    }

    public function cancel(User $user, Fatura $fatura): bool
    {
        return $this->checkAbility($user, 'faturas.cancel');
    }

    public function email(User $user, Fatura $fatura): bool
    {
        return $this->checkAbility($user, 'faturas.email');
    }

    private function checkAbility(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
