<?php

namespace App\Policies;

use App\Models\PaymentSchedule;
use App\Models\User;

class PaymentSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('financeiro.view');
    }

    public function view(User $user, PaymentSchedule $schedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('financeiro.create');
    }

    public function update(User $user, PaymentSchedule $schedule): bool
    {
        if ($schedule->status === 'cancelado') {
            return false;
        }

        return $user->hasPermission('financeiro.update');
    }

    public function delete(User $user, PaymentSchedule $schedule): bool
    {
        return $user->hasPermission('financeiro.delete');
    }
}
