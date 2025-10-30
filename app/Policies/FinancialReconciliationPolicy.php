<?php

namespace App\Policies;

use App\Models\FinancialReconciliation;
use App\Models\User;

class FinancialReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, FinancialReconciliation $reconciliation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }

    public function update(User $user, FinancialReconciliation $reconciliation): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }

    public function delete(User $user, FinancialReconciliation $reconciliation): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }
}
