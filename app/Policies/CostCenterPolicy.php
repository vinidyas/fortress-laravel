<?php

namespace App\Policies;

use App\Models\CostCenter;
use App\Models\User;

class CostCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.create');
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $user->hasPermission('financeiro.update');
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        if ($costCenter->journalEntries()->exists()) {
            return false;
        }

        if ($costCenter->transactions()->exists()) {
            return false;
        }

        return $user->hasPermission('financeiro.delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('financeiro.export');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('financeiro.create');
    }
}
