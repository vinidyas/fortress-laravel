<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;

class FinancialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.create');
    }

    public function update(User $user, FinancialAccount $account): bool
    {
        return $user->hasPermission('financeiro.update');
    }

    public function delete(User $user, FinancialAccount $account): bool
    {
        if ($account->journalEntries()->exists() || $account->counterJournalEntries()->exists()) {
            return false;
        }

        if ($account->transactions()->exists()) {
            return false;
        }

        return $user->hasPermission('financeiro.delete');
    }
}
