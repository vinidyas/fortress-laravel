<?php

namespace App\Policies;

use App\Models\FinancialTransaction;
use App\Models\User;

class FinancialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.create');
    }

    public function update(User $user, FinancialTransaction $transaction): bool
    {
        if ($transaction->status === 'conciliado') {
            return $user->hasPermission('financeiro.reconcile');
        }

        return $user->hasPermission('financeiro.update');
    }

    public function delete(User $user, FinancialTransaction $transaction): bool
    {
        if ($transaction->status === 'conciliado') {
            return false;
        }

        return $user->hasPermission('financeiro.delete');
    }

    public function reconcile(User $user, FinancialTransaction $transaction): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('financeiro.export');
    }
}
