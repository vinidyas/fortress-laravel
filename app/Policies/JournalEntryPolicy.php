<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.create');
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        if ($entry->status === 'pago') {
            return $user->hasPermission('financeiro.update');
        }

        if ($entry->status === 'cancelado') {
            return false;
        }

        return $user->hasPermission('financeiro.update');
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        if ($entry->status === 'pago') {
            return false;
        }

        return $user->hasPermission('financeiro.delete');
    }

    public function reconcile(User $user, JournalEntry $entry): bool
    {
        return $user->hasPermission('financeiro.reconcile');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('financeiro.export');
    }
}
