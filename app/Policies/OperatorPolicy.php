<?php

namespace App\Policies;

use App\Models\Operator;
use App\Models\User;

/**
 * Policy Operator — OWASP A01 (Broken Access Control).
 *
 * Lecture : manager + admin (operator.view / operator.view)
 * Écriture : agent peut créer/modifier (operator.create / operator.update)
 * Suppression : admin/manager uniquement (operator.delete)
 */
class OperatorPolicy
{
    public function view(User $user, Operator $operator): bool
    {
        return $user->can('operator.view') || $user->hasPermissionOverride('operator.view');
    }

    public function create(User $user): bool
    {
        return $user->can('operator.create') || $user->hasPermissionOverride('operator.create');
    }

    public function update(User $user, Operator $operator): bool
    {
        return $user->can('operator.update') || $user->hasPermissionOverride('operator.update');
    }

    public function delete(User $user, Operator $operator): bool
    {
        return $user->can('operator.delete') || $user->hasPermissionOverride('operator.delete');
    }
}
