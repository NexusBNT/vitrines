<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

/**
 * Réservé aux administrateurs. Les suppressions sont interdites :
 * on désactive plutôt que de supprimer, pour conserver l'historique.
 */
class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Server $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Server $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Server $model): bool
    {
        return false;
    }
}
