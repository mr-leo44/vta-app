<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gestion des overrides de permissions par utilisateur.
 *
 * GET    /api/users/{user}/permissions         → liste des overrides + permissions effectives
 * POST   /api/users/{user}/permissions/grant   → accorde une permission
 * POST   /api/users/{user}/permissions/revoke  → révoque une permission
 * DELETE /api/users/{user}/permissions/{perm}  → supprime un override (reset to role default)
 */
class UserPermissionController extends Controller
{
    /**
     * Liste les overrides actifs et les permissions effectives d'un utilisateur.
     * Admin uniquement (user.view).
     */
    public function index(User $user): JsonResponse
    {
        $this->authorize('view', User::class);

        $user->load(['permissionOverrides.grantedBy:id,name', 'currentFunction']);

        return response()->json([
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'role'     => $user->getRoleNames()->first(),
                'function' => $user->currentFunction?->function,
            ],
            'role_permissions'      => $user->getAllPermissions()->pluck('name')->sort()->values(),
            'effective_permissions' => $user->effectivePermissions()->sort()->values(),
            'overrides'             => $user->permissionOverrides->map(fn ($o) => [
                'id'         => $o->id,
                'permission' => $o->permission,
                'type'       => $o->type,
                'reason'     => $o->reason,
                'granted_by' => $o->grantedBy?->name,
                'created_at' => $o->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Accorde une permission supplémentaire (en dehors du rôle).
     */
    public function grant(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignFunction', $user); // réutilise user.assignFunction

        $data = $request->validate([
            'permission' => ['required', Rule::enum(PermissionEnum::class)],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);


        // updateOrCreate — si un revoke existait, on le remplace par un grant
        $override = UserPermissionOverride::updateOrCreate(
            ['user_id' => $user->id, 'permission' => $data['permission']],
            [
                'type'       => 'grant',
                'granted_by' => Auth::id(),
                'reason'     => $data['reason'] ?? null,
            ]
        );

        // S'assurer que la permission est également attribuée via Spatie
        // (persistée dans model_has_permissions) pour compatibilité avec
        // d'autres usages de la librairie.
        $user->givePermissionTo($data['permission']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        AuditLog::record(
            event:     'permission_granted',
            subject:   $user,
            newValues: [
                'permission' => $data['permission'],
                'reason'     => $data['reason'] ?? null,
            ],
        );

        return response()->json([
            'message'              => 'Permission accordée.',
            'override'             => $override,
            'effective_permissions'=> $user->effectivePermissions(),
        ]);
    }

    /**
     * Révoque une permission (même si le rôle la donne normalement).
     */
    public function revoke(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignFunction', $user);

        // Interdit de révoquer des permissions à un admin
        abort_if($user->hasRole('admin'), 403, 'Les permissions des administrateurs ne peuvent pas être révoquées.');

        $data = $request->validate([
            'permission' => ['required', Rule::enum(PermissionEnum::class)],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);


        $override = UserPermissionOverride::updateOrCreate(
            ['user_id' => $user->id, 'permission' => $data['permission']],
            [
                'type'       => 'revoke',
                'granted_by' => Auth::id(),
                'reason'     => $data['reason'] ?? null,
            ]
        );

        // Si l'utilisateur avait reçu la permission directement via Spatie,
        // la retirer pour garder la source de vérité cohérente.
        if ($user->hasDirectPermission($data['permission'])) {
            $user->revokePermissionTo($data['permission']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        AuditLog::record(
            event:     'permission_revoked',
            subject:   $user,
            newValues: [
                'permission' => $data['permission'],
                'reason'     => $data['reason'] ?? null,
            ],
        );

        return response()->json([
            'message'               => 'Permission révoquée.',
            'effective_permissions' => $user->effectivePermissions(),
        ]);
    }

    /**
     * Supprime un override — remet la permission à son état par défaut (celui du rôle).
     */
    public function destroy(User $user, string $permission): JsonResponse
    {
        $this->authorize('assignFunction', $user);

        $override = UserPermissionOverride::where('user_id', $user->id)
            ->where('permission', $permission)
            ->first();

        abort_unless($override, 404, 'Override introuvable.');

        // Si l'override supprimé était un grant, retirer aussi la permission
        // directe stockée par Spatie pour éviter d'avoir une permission
        // persistée sans override correspondant.
        if ($override->type === 'grant' && $user->hasDirectPermission($permission)) {
            $user->revokePermissionTo($permission);
        }

        $override->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message'               => 'Override supprimé — permission réinitialisée au rôle.',
            'effective_permissions' => $user->effectivePermissions(),
        ]);
    }

    /**
     * Liste toutes les demandes de permissions (overrides) de tous les utilisateurs.
     * Admin uniquement (user.view).
     *
     * Retourne les grants et revokes avec infos utilisateur et administrateur.
     */
    public function listRequests(): JsonResponse
    {
        $this->authorize('view', User::class);

        $overrides = UserPermissionOverride::with(['user:id,name', 'grantedBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($override) => [
                'id'         => $override->id,
                'user'       => [
                    'id'   => $override->user->id,
                    'name' => $override->user->name,
                ],
                'permission'  => $override->permission,
                'type'        => $override->type,
                'reason'      => $override->reason,
                'granted_by'  => $override->grantedBy?->name,
                'created_at'  => $override->created_at->toIso8601String(),
            ]);

        return response()->json([
            'requests' => $overrides,
            'total'    => $overrides->count(),
            'message'  => 'Liste de toutes les demandes de permissions.',
        ]);
    }

    /**
     * Liste TOUTES les permissions disponibles (avec descriptions).
     * Admin uniquement (user.view).
     *
     * Utile pour afficher une liste de permissions à grant/revoke au front.
     */
    public function listPermissions(): JsonResponse
    {
        $this->authorize('view', User::class);

        $permissionsByCategory = [
            'Vols' => [
                'flight.view'       => 'Voir les vols',
                'flight.create'     => 'Créer des vols',
                'flight.updateOwn'  => 'Modifier ses propres vols',
                'flight.updateAny'  => 'Modifier tous les vols',
                'flight.deleteOwn'  => 'Supprimer ses propres vols',
                'flight.deleteAny'  => 'Supprimer tous les vols',
                'flight.validate'   => 'Valider les vols',
                'flight.export'     => 'Exporter les vols',
            ],
            'Avions' => [
                'aircraft.view'     => 'Voir les avions',
                'aircraft.create'   => 'Créer des avions',
                'aircraft.update'   => 'Modifier les avions',
                'aircraft.delete'   => 'Supprimer les avions',
            ],
            'Types d\'avion' => [
                'aircraftType.view'   => 'Voir les types d\'avion',
                'aircraftType.create' => 'Créer des types d\'avion',
                'aircraftType.update' => 'Modifier les types d\'avion',
                'aircraftType.delete' => 'Supprimer les types d\'avion',
            ],
            'Opérateurs' => [
                'operator.view'     => 'Voir les opérateurs',
                'operator.create'   => 'Créer des opérateurs',
                'operator.update'   => 'Modifier les opérateurs',
                'operator.delete'   => 'Supprimer les opérateurs',
            ],
            'Rapports' => [
                'report.view'       => 'Voir les rapports',
                'report.export'     => 'Exporter les rapports',
            ],
            'Utilisateurs' => [
                'user.view'                  => 'Voir les utilisateurs',
                'user.create'                => 'Créer des utilisateurs',
                'user.update'                => 'Modifier les utilisateurs',
                'user.delete'                => 'Supprimer les utilisateurs',
                'user.assignFunction'        => 'Assigner une fonction',
                'user.resetPassword'         => 'Réinitialiser le mot de passe',
                'user.resetPasswordRequest'  => 'Demander une réinitialisation de mot de passe',
                'user.updateProfile'         => 'Mettre à jour son profil',
                'user.changePassword'        => 'Changer son mot de passe',
            ],
            'Permissions' => [
                'permission.view'            => 'Voir les permissions',
                'permission.viewOwn'         => 'Voir ses propres permissions',
                'permissionRequest.create'   => 'Créer une demande de permission',
                'permissionRequest.manage'   => 'Gérer les demandes de permissions',
            ],
            'Imports & Exports' => [
                'files.import'      => 'Importer des fichiers',
                'files.export'      => 'Exporter des fichiers',
            ],
            'Données de trafic' => [
                'pax.update'        => 'Modifier les données PAX',
                'freight.update'    => 'Modifier les données de fret',
                'excedent.update'   => 'Modifier les données d\'excédent',
            ],
            'IDEF' => [
                'gopass.update'     => 'Mettre à jour GOPASS',
            ],
            'PAX BUS' => [
                'paxbus.update'     => 'Mettre à jour PAX BUS',
            ],
        ];

        // Transformer en array simple avec catégories
        $permissions = [];
        foreach ($permissionsByCategory as $category => $perms) {
            foreach ($perms as $permission => $description) {
                $permissions[] = [
                    'permission'  => $permission,
                    'category'    => $category,
                    'description' => $description,
                ];
            }
        }

        return response()->json([
            'permissions' => $permissions,
            'message'     => 'Liste complète des permissions disponibles.',
        ]);
    }
}
