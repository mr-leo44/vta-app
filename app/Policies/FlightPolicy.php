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
    public function viewAny(User $user): bool
    {
        return $user->can('flight.viewAny');
    }

    public function view(User $user, Flight $flight): bool
    {
        return $user->can('flight.view');
    }

    public function create(User $user): bool
    {
        return $user->can('flight.create');
    }

    /**
     * OWASP A01 — vérification d'ownership.
     *
     * - Admin / Manager : flight.updateAny → tous les vols
     * - Agent : flight.updateOwn → uniquement ses vols non validés
     */
    public function update(User $user, Flight $flight): bool
    {
        if ($user->can('flight.updateAny')) {
            return true;
        }

        if ($user->can('flight.updateOwn')) {
            return (int) $flight->created_by === $user->id
                && ! $flight->is_validated;
        }

        return false;
    }

    public function delete(User $user, Flight $flight): bool
    {
        if ($user->can('flight.deleteAny')) {
            return true;
        }

        if ($user->can('flight.deleteOwn')) {
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
        return $user->can('flight.validate') && ! $flight->is_validated;
    }

    public function export(User $user): bool
    {
        return $user->can('flight.export');
    }
}
