<?php

namespace App\Policies;

use App\Models\AircraftType;
use App\Models\User;

/**
 * Policy AircraftType — OWASP A01 (Broken Access Control).
 *
 * Lecture : tous les rôles authentifiés (aircraftType.view / aircraftType.view)
 * Écriture : agent peut créer/modifier (aircraftType.create / aircraftType.update)
 * Suppression : admin/manager uniquement (aircraftType.delete)
 */
class AircraftTypePolicy
{
    public function view(User $user): bool
    {
        return $user->can('aircraftType.view') || $user->hasPermissionOverride('aircraftType.view');
    }

    public function create(User $user): bool
    {
        return $user->can('aircraftType.create') || $user->hasPermissionOverride('aircraftType.create');
    }

    public function update(User $user, AircraftType $aircraftType): bool
    {
        return $user->can('aircraftType.update') || $user->hasPermissionOverride('aircraftType.update');
    }

    public function delete(User $user, AircraftType $aircraftType): bool
    {
        return $user->can('aircraftType.delete') || $user->hasPermissionOverride('aircraftType.delete');
    }
}