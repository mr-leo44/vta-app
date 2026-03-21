<?php

namespace App\Models;

use App\Enums\UserFunction;
use App\Enums\UserRole;
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

    /**
     * Tout l'historique de fonctions — PAS d'orderBy ici,
     * orderBy sur HasMany est incompatible avec create().
     */
    public function functionHistories(): HasMany
    {
        return $this->hasMany(UserFunctionHistory::class);
    }

    /**
     * Fonction actuellement active (end_date IS NULL).
     *
     * IMPORTANT : on utilise HasOne + latest(), jamais latestOfMany().
     * latestOfMany() est une méthode de HasMany — sur HasOne elle génère
     * une query corrompue qui tente d'insérer dans `users` au lieu de
     * `user_function_histories` (SQLSTATE 42S22 column user_id not found).
     */
    public function currentFunction(): HasOne
    {
        return $this->hasOne(UserFunctionHistory::class)
                    ->whereNull('end_date')
                    ->latest('start_date');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers métier
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
    // Assignation de fonction
    // ─────────────────────────────────────────────────────────────────────

    public function assignFunction(UserFunction $function, ?string $start_date = null): UserFunctionHistory
    {
        $start_date ??= now()->toDateString();

        // Contrainte singleton : ferme l'occurrence active chez un autre user
        if ($function->isSingleton()) {
            UserFunctionHistory::query()
                ->where('function', $function->value)
                ->whereNull('end_date')
                ->where('user_id', '!=', $this->id)
                ->update(['end_date' => $start_date]);
        }

        // Ferme la fonction courante de cet utilisateur.
        // IMPORTANT : accès en propriété ($this->currentFunction), PAS en méthode
        // ($this->currentFunction()) — la méthode retourne le QueryBuilder, pas
        // le modèle, ce qui empêche ->update() de fonctionner correctement.
        $this->currentFunction?->update(['end_date' => $start_date]);

        // Recharge la relation pour éviter un cache Eloquent périmé
        $this->unsetRelation('currentFunction');

        // Crée le nouvel enregistrement d'historique
        $history = $this->functionHistories()->create([
            'function'   => $function->value,
            'start_date' => $start_date,
        ]);

        // Synchronise le rôle Spatie — syncRoles() remplace tous les rôles existants
        $this->syncRoles([$function->role()->value]);

        // Vide le cache Spatie — sinon l'ancien rôle reste actif jusqu'à expiration
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $history;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Résumé des accès (GET /api/user → hydratation Nuxt Pinia)
    // ─────────────────────────────────────────────────────────────────────

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
