<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * AuditObserver — version base de données.
 *
 * Insère dans audit_logs (plus de Log::channel fichier).
 * Remplit created_by / updated_by avant persistence si la colonne existe.
 */
class AuditObserver
{
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

    public function created(Model $model): void
    {
        $newValues = $model->getAttributes();
        if(isset($newValues['password'])) unset($newValues['password']);
        $this->record('created', $model, newValues: $newValues);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if (isset($changes['password'])) unset($changes['password']);
        unset($changes['updated_at'], $changes['updated_by']);

        if (empty($changes)) {
            return;
        }

        $this->record('updated', $model,
            oldValues: array_intersect_key($model->getOriginal(), $changes),
            newValues: $changes,
        );
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, oldValues: $model->getAttributes());
    }

    public function restored(Model $model): void
    {
        $this->record('restored', $model, newValues: $model->getAttributes());
    }

    private function record(
        string $event,
        Model  $model,
        array  $oldValues = [],
        array  $newValues = [],
    ): void {
        // Évite la récursion infinie si AuditLog lui-même était observé
        if ($model instanceof AuditLog) {
            return;
        }

        AuditLog::create([
            'event'          => $event,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'actor_id'       => Auth::id(),
            'actor_ip'       => request()->ip(),
            'actor_agent'    => substr((string) request()->userAgent(), 0, 255),
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
        ]);
    }
}
