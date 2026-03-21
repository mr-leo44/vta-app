<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modèle AuditLog — lecture seule.
 *
 * Les logs ne se créent QUE via AuditObserver ou les helpers statiques.
 * Jamais via ->update() ou ->save() directement.
 *
 * @property string $event
 * @property string $auditable_type
 * @property int    $auditable_id
 * @property int|null $actor_id
 * @property string|null $actor_ip
 * @property string|null $actor_agent
 * @property array|null  $old_values
 * @property array|null  $new_values
 * @property \Carbon\Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // pas de updated_at — un log est immuable

    protected $fillable = [
        'event',
        'auditable_type',
        'auditable_id',
        'actor_id',
        'actor_ip',
        'actor_agent',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values'  => 'array',
            'new_values'  => 'array',
            'created_at'  => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes (pour la page audit côté front)
    // ─────────────────────────────────────────────────────────────────────

    public function scopeForModel(Builder $query, string $type, ?int $id = null): Builder
    {
        $query->where('auditable_type', $type);
        if ($id) {
            $query->where('auditable_id', $id);
        }
        return $query;
    }

    public function scopeByActor(Builder $query, int $actorId): Builder
    {
        return $query->where('actor_id', $actorId);
    }

    public function scopeByEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to . ' 23:59:59']);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helper statique — écriture manuelle (ex: function_assigned)
    // ─────────────────────────────────────────────────────────────────────

    public static function record(
        string  $event,
        Model   $subject,
        array   $newValues  = [],
        array   $oldValues  = [],
        ?int    $actorId    = null,
        ?string $actorIp    = null,
        ?string $actorAgent = null,
    ): self {
        return self::create([
            'event'          => $event,
            'auditable_type' => get_class($subject),
            'auditable_id'   => $subject->getKey(),
            'actor_id'       => $actorId   ?? auth()->id(),
            'actor_ip'       => $actorIp   ?? request()->ip(),
            'actor_agent'    => $actorAgent ?? substr((string) request()->userAgent(), 0, 255),
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers de lecture
    // ─────────────────────────────────────────────────────────────────────

    /** Nom court du modèle audité (ex: "Flight" au lieu du FQCN). */
    public function auditableLabel(): string
    {
        return class_basename($this->auditable_type);
    }

    /** Libellé lisible de l'événement. */
    public function eventLabel(): string
    {
        return match ($this->event) {
            'created'            => 'Créé',
            'updated'            => 'Modifié',
            'deleted'            => 'Supprimé',
            'restored'           => 'Restauré',
            'function_assigned'  => 'Fonction assignée',
            'permission_granted' => 'Permission accordée',
            'permission_revoked' => 'Permission révoquée',
            default              => ucfirst($this->event),
        };
    }
}
