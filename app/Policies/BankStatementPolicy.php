<?php

namespace App\Policies;

use App\Models\BankStatement;
use App\Models\User;

class BankStatementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, BankStatement $statement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }

    public function update(User $user, BankStatement $statement): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }

    public function delete(User $user, BankStatement $statement): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }
}
