<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Réservé aux administrateurs. Les suppressions sont interdites :
 * on désactive plutôt que de supprimer, pour conserver l'historique.
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Plan $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Plan $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Plan $model): bool
    {
        return false;
    }
}
