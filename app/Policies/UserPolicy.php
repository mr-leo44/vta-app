<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy User.
 *
 * Seul l'admin peut gérer les utilisateurs et assigner des fonctions.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $target): bool
    {
        // Un utilisateur ne peut pas se modifier lui-même via l'API admin
        if ($user->id === $target->id) {
            return false;
        }

        return $user->can('user.update');
    }

    public function delete(User $user, User $target): bool
    {
        // Interdit l'auto-suppression
        if ($user->id === $target->id) {
            return false;
        }

        return $user->can('user.delete');
    }

    public function assignFunction(User $user, User $target): bool
    {
        return $user->can('user.assignFunction');
    }
}
