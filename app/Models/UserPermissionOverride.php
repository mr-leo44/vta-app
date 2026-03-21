<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override de permission individuel pour un utilisateur.
 *
 * type = 'grant'  → la permission est ajoutée même si le rôle ne la donne pas
 * type = 'revoke' → la permission est retirée même si le rôle la donne
 */
class UserPermissionOverride extends Model
{
    protected $fillable = [
        'user_id',
        'permission',
        'type',
        'granted_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'string',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeGrants(Builder $query): Builder
    {
        return $query->where('type', 'grant');
    }

    public function scopeRevokes(Builder $query): Builder
    {
        return $query->where('type', 'revoke');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function isGrant(): bool
    {
        return $this->type === 'grant';
    }

    public function isRevoke(): bool
    {
        return $this->type === 'revoke';
    }

    /** Libellé lisible de la permission (depuis l'enum). */
    public function permissionLabel(): string
    {
        return Permission::tryFrom($this->permission)?->name ?? $this->permission;
    }
}
