<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Observer d'audit générique.
 *
 * À attacher sur : Flight, Aircraft, AircraftType, Operator
 * via AppServiceProvider::boot().
 *
 * Responsabilités :
 *  - Remplit automatiquement created_by / updated_by avant persitance
 *  - Journalise chaque événement dans le canal "audit" avec l'identité de l'acteur
 */
class AuditObserver
{
    // ─────────────────────────────────────────────────────────────────────
    // Hooks Eloquent (avant persistence)
    // ─────────────────────────────────────────────────────────────────────

    public function creating(Model $model): void
    {
        if (Auth::check() && in_array('created_by', $model->getFillable(), true)) {
            $model->created_by = Auth::id();
        }
    }

    public function updating(Model $model): void
    {
        if (Auth::check() && in_array('updated_by', $model->getFillable(), true)) {
            $model->updated_by = Auth::id();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Hooks Eloquent (après persistence → log)
    // ─────────────────────────────────────────────────────────────────────

    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        // Ne log que les changements réels (getChanges() exclut les champs inchangés)
        $changes = $model->getChanges();
        unset($changes['updated_at'], $changes['updated_by']);

        if (! empty($changes)) {
            $this->log('updated', $model, [
                'old' => array_intersect_key($model->getOriginal(), $changes),
                'new' => $changes,
            ]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    public function restored(Model $model): void
    {
        $this->log('restored', $model);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Logging interne
    // ─────────────────────────────────────────────────────────────────────

    private function log(string $event, Model $model, array $diff = []): void
    {
        Log::channel('audit')->info($event, [
            'model'      => class_basename($model),
            'id'         => $model->getKey(),
            'actor_id'   => Auth::id(),
            'actor_name' => Auth::user()?->name,
            'actor_ip'   => request()->ip(),
            'user_agent' => request()->userAgent(),
            'diff'       => $diff,
        ]);
    }
}
