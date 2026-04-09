<?php

namespace App\Policies;

use App\Models\Aircraft;
use App\Models\User;

/**
 * Policy Aircraft — OWASP A01 (Broken Access Control).
 *
 * Lecture : tous les rôles authentifiés (aircraft.view / aircraft.view)
 * Écriture : agent peut créer/modifier (aircraft.create / aircraft.update)
 * Suppression : admin/manager uniquement (aircraft.delete)
 */
class AircraftPolicy
{
    public function view(User $user, Aircraft $aircraft): bool
    {
        return $user->can('aircraft.view') || $user->hasPermissionOverride('aircraft.view');
    }

    public function create(User $user): bool
    {
        return $user->can('aircraft.create') || $user->hasPermissionOverride('aircraft.create');
    }

    public function update(User $user, Aircraft $aircraft): bool
    {
        return $user->can('aircraft.update') || $user->hasPermissionOverride('aircraft.update');
    }

    public function delete(User $user, Aircraft $aircraft): bool
    {
        return $user->can('aircraft.delete') || $user->hasPermissionOverride('aircraft.delete');
    }
}