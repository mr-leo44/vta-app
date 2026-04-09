<?php

namespace App\Policies;

use App\Models\Flight;
use App\Models\User;

/**
 * Policy Flight — OWASP A01 (Broken Access Control).
 *
 * Principe clé : un agent ne peut agir que sur SES propres vols
 * tant qu'ils ne sont pas encore validés.
 * Un manager/admin peut agir sur tous les vols.
 *
 * NB : on utilise $user->can() et non hasPermissionTo() pour bénéficier
 * du Gate Laravel (qui respecte les super-admins définis via Gate::before).
 */
class FlightPolicy
{
    public function view(User $user): bool
    {
        return $user->can('flight.view') || $user->hasPermissionOverride('flight.view');
    }

    public function create(User $user): bool
    {
        return $user->can('flight.create') || $user->hasPermissionOverride('flight.create');
    }

    /**
     * OWASP A01 — vérification d'ownership.
     *
     * - Admin / Manager : flight.updateAny → tous les vols
     * - Agent : flight.updateOwn → uniquement ses vols non validés
     */
    public function update(User $user, Flight $flight): bool
    {
        $hasAny = $user->can('flight.updateAny') || $user->hasPermissionOverride('flight.updateAny');
        $hasOwn = $user->can('flight.updateOwn') || $user->hasPermissionOverride('flight.updateOwn');

        if ($hasAny) {
            return true;
        }

        if ($hasOwn) {
            return (int) $flight->created_by === $user->id
                && ! $flight->is_validated;
        }

        return false;
    }

    public function delete(User $user, Flight $flight): bool
    {
        $hasAny = $user->can('flight.deleteAny') || $user->hasPermissionOverride('flight.deleteAny');
        $hasOwn = $user->can('flight.deleteOwn') || $user->hasPermissionOverride('flight.deleteOwn');

        if ($hasAny) {
            return true;
        }

        if ($hasOwn) {
            return (int) $flight->created_by === $user->id
                && ! $flight->is_validated;
        }

        return false;
    }

    /**
     * Validation d'un vol — réservé à l'admin via flight.validate.
     * Un vol déjà validé ne peut pas être re-validé.
     */
    public function validate(User $user, Flight $flight): bool
    {
        return ($user->can('flight.validate') && ! $flight->is_validated) || $user->hasPermissionOverride('flight.validate');
    }

    public function export(User $user): bool
    {
        return $user->can('flight.export') || $user->hasPermissionOverride('flight.export');
    }
}
