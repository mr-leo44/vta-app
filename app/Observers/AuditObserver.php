<?php

namespace App\Observers;

use App\Helpers\AuditContext;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * AuditObserver — remplit created_by/updated_by automatiquement.
 *
 * - Si Auth::check() → utilise Auth::id()
 * - Sinon, essaie model->getAttribute('created_by') ou model->getAttribute('updated_by')
 * - Insère aussi audit_logs pour chaque create/update/delete
 */
class AuditObserver
{
    /**
     * Hook avant la création — remplit created_by si absent.
     */
    public function creating(Model $model): void
    {
        if (in_array('created_by', $model->getFillable(), true)) {
            // Remplit seulement si vide
            if (!$model->getAttribute('created_by')) {
                // Essayer Auth d'abord, puis AuditContext (pour les jobs)
                $userId = Auth::id() ?? AuditContext::getUserId();
                $model->created_by = $userId;
            }
        }
    }

    /**
     * Hook avant la modif — remplit updated_by si absent.
     */
    public function updating(Model $model): void
    {
        if (in_array('updated_by', $model->getFillable(), true)) {
            if (!$model->getAttribute('updated_by')) {
                $userId = Auth::id() ?? AuditContext::getUserId();
                $model->updated_by = $userId;
            }
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

        // Récupérer l'user_id : Auth, puis AuditContext, puis null
        $actorId = Auth::id() ?? AuditContext::getUserId();

        AuditLog::create([
            'event'          => $event,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'actor_id'       => $actorId,
            'actor_ip'       => request()?->ip(),
            'actor_agent'    => request() ? substr((string) request()->userAgent(), 0, 255) : null,
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
        ]);
    }
}
