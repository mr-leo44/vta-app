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
    public function view(User $user): bool
    {
        return $user->can('user.view') || $user->hasPermissionOverride('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create') || $user->hasPermissionOverride('user.create');
    }

    public function update(User $user, User $target): bool
    {
        // Un utilisateur ne peut pas se modifier lui-même via l'API admin
        if ($user->id === $target->id) {
            return false;
        }

        return $user->can('user.update') || $user->hasPermissionOverride('user.update');
    }

    public function delete(User $user, User $target): bool
    {
        // Interdit l'auto-suppression
        if ($user->id === $target->id) {
            return false;
        }

        return $user->can('user.delete') || $user->hasPermissionOverride('user.delete');
    }

    public function assignFunction(User $user, User $target): bool
    {
        return $user->can('user.assignFunction') || $user->hasPermissionOverride('user.assignFunction');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->can('user.resetPassword') || $user->hasPermissionOverride('user.resetPassword');
    }

    public function resetPaswwordRequest(User $user, User $target): bool
    {
        return $user->can('user.resetPasswordRequest') || $user->hasPermissionOverride('user.resetPasswordRequest');
    }
}
