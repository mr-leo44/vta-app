<?php

namespace App\Models;

use App\Enums\UserFunction;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = ['name', 'username', 'password'];
    protected $hidden   = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    /** Tout l'historique — PAS d'orderBy (incompatible avec create()). */
    public function functionHistories(): HasMany
    {
        return $this->hasMany(UserFunctionHistory::class);
    }

    /**
     * Fonction active. HasOne + latest() — jamais latestOfMany().
     * latestOfMany() sur HasOne génère une INSERT dans users (SQLSTATE 42S22).
     */
    public function currentFunction(): HasOne
    {
        return $this->hasOne(UserFunctionHistory::class)
                    ->whereNull('end_date')
                    ->latest('start_date');
    }

    /** Overrides manuels de permissions (grants et revokes). */
    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    /**
     * Indique si un override existe pour l'utilisateur.
     * Si $type est fourni, filtre sur 'grant' ou 'revoke'.
     */
    public function hasPermissionOverride(string $permission, ?string $type = null): bool
    {
        $query = $this->permissionOverrides()->where('permission', $permission);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->exists();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers — fonction & rôle
    // ─────────────────────────────────────────────────────────────────────

    public function currentFunctionEnum(): ?UserFunction
    {
        $value = $this->currentFunction?->function;
        return $value ? UserFunction::tryFrom($value) : null;
    }

    public function currentRoleEnum(): ?UserRole
    {
        return $this->currentFunctionEnum()?->role();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Permissions effectives
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcule les permissions finales :
     *   = permissions du rôle Spatie
     *   + grants manuels
     *   - revokes manuels
     */
    public function effectivePermissions(): Collection
    {
        $rolePermissions = $this->getAllPermissions()->pluck('name');
        $overrides       = $this->permissionOverrides()->get();

        $grants  = $overrides->where('type', 'grant')->pluck('permission');
        $revokes = $overrides->where('type', 'revoke')->pluck('permission');

        return $rolePermissions
            ->merge($grants)
            ->unique()
            ->diff($revokes)
            ->values();
    }

    /**
     * Override de can() pour bloquer les permissions révoquées manuellement.
     * Les admins ne sont PAS soumis aux revokes (sécurité).
     */
    public function can($abilities, $arguments = []): bool
    {
        if ($this->hasRole('admin')) {
            return parent::can($abilities, $arguments);
        }

        if (is_string($abilities)) {
            $revoked = $this->permissionOverrides()
                ->where('type', 'revoke')
                ->where('permission', $abilities)
                ->exists();

            if ($revoked) {
                return false;
            }
        }

        return parent::can($abilities, $arguments);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assignation de fonction
    // ─────────────────────────────────────────────────────────────────────

    public function assignFunction(UserFunction $function, ?string $start_date = null): UserFunctionHistory
    {
        $start_date       = $start_date ?? now()->toDateString();
        $previousFunction = $this->currentFunction?->function;

        if ($function->isSingleton()) {
            UserFunctionHistory::query()
                ->where('function', $function->value)
                ->whereNull('end_date')
                ->where('user_id', '!=', $this->id)
                ->update(['end_date' => $start_date]);
        }

        $this->currentFunction?->update(['end_date' => $start_date]);
        $this->unsetRelation('currentFunction');

        $history = $this->functionHistories()->create([
            'function'   => $function->value,
            'start_date' => $start_date,
        ]);

        $this->syncRoles([$function->role()->value]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        AuditLog::record(
            event:     'function_assigned',
            subject:   $this,
            oldValues: $previousFunction ? ['function' => $previousFunction] : [],
            newValues: [
                'function'   => $function->value,
                'role'       => $function->role()->value,
                'start_date' => $start_date,
            ],
        );

        return $history;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Résumé des accès → GET /api/user (hydratation Nuxt Pinia)
    // ─────────────────────────────────────────────────────────────────────

    public function accessSummary(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'username'    => $this->username,
            'function'    => $this->currentFunction?->function,
            'role'        => $this->getRoleNames()->first(),
            'permissions' => $this->effectivePermissions(),
            'overrides'   => $this->permissionOverrides()
                ->with('grantedBy:id,name')
                ->get()
                ->map(fn ($o) => [
                    'permission' => $o->permission,
                    'type'       => $o->type,
                    'reason'     => $o->reason,
                    'granted_by' => $o->grantedBy?->name,
                    'created_at' => $o->created_at->toIso8601String(),
                ]),
        ];
    }
}
