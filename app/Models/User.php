<?php

namespace App\Models;

use App\Enums\UserFunction;
use App\Enums\UserRole;
use App\Models\UserFunctionHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    public function functionHistories(): HasMany
    {
        return $this->hasMany(User::class)
                    ->orderBy('start_date', 'desc');
    }

    public function currentFunction(): HasOne
    {
        return $this->hasOne(UserFunctionHistory::class)
                    ->whereNull('end_date')
                    ->latestOfMany('start_date');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers métier
    // ─────────────────────────────────────────────────────────────────────

    /** Retourne la fonction courante en tant qu'enum (ou null). */
    public function currentFunctionEnum(): ?UserFunction
    {
        $value = $this->currentFunction?->function;
        return $value ? UserFunction::tryFrom($value) : null;
    }

    /** Retourne le rôle courant en tant qu'enum (ou null). */
    public function currentRoleEnum(): ?UserRole
    {
        return $this->currentFunctionEnum()?->role();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assignation de fonction
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Assigne une fonction à l'utilisateur.
     *
     * - Ferme toute occurrence active singleton de cette fonction (autre user)
     * - Ferme la fonction courante de cet utilisateur
     * - Crée le nouvel enregistrement d'historique
     * - Synchronise le rôle Spatie Permission
     * - Vide le cache des permissions
     *
     * @throws \InvalidArgumentException
     */
    public function assignFunction(UserFunction $function, ?string $start_date = null): UserFunctionHistory
    {
        $start_date ??= now()->toDateString();

        // Contrainte singleton : un seul titulaire actif à la fois
        if ($function->isSingleton()) {
            UserFunctionHistory::query()
                ->where('function', $function->value)
                ->whereNull('end_date')
                ->where('user_id', '!=', $this->id)
                ->update(['end_date' => $start_date]);
        }

        // Ferme la fonction courante de l'utilisateur
        $this->currentFunction()?->first()?->update(['end_date' => $start_date]);

        // Crée le nouvel historique
        $history = $this->functionHistories()->create([
            'function'   => $function->value,
            'start_date' => $start_date,
        ]);

        // ── Sync rôle Spatie ─────────────────────────────────────────────
        // syncRoles() remplace tous les rôles existants → toujours un seul rôle actif
        $this->syncRoles([$function->role()->value]);

        // Invalide le cache Spatie (sinon l'ancien rôle reste actif jusqu'à expiration)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $history;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Résumé des accès (pour le endpoint /user → Nuxt)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retourne le profil d'accès complet de l'utilisateur.
     * Utilisé par GET /api/user pour hydrater le store Pinia côté Nuxt.
     */
    public function accessSummary(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'username'    => $this->username,
            'function'    => $this->currentFunction?->function,
            'role'        => $this->getRoleNames()->first(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
