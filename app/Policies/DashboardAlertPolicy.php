<?php

namespace App\Policies;

use App\Models\DashboardAlert;
use App\Models\User;

class DashboardAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('alerts.view');
    }

    public function view(User $user, DashboardAlert $alert): bool
    {
        return $this->viewAny($user);
    }

    public function resolve(User $user, DashboardAlert $alert): bool
    {
        return $user->hasPermission('alerts.resolve');
    }
}
