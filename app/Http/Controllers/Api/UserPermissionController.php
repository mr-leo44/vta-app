<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                'granted_by' => auth()->id(),
                'reason'     => $data['reason'] ?? null,
            ]
        );

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

        UserPermissionOverride::updateOrCreate(
            ['user_id' => $user->id, 'permission' => $data['permission']],
            [
                'type'       => 'revoke',
                'granted_by' => auth()->id(),
                'reason'     => $data['reason'] ?? null,
            ]
        );

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

        $deleted = UserPermissionOverride::where('user_id', $user->id)
            ->where('permission', $permission)
            ->delete();

        abort_unless($deleted, 404, 'Override introuvable.');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message'               => 'Override supprimé — permission réinitialisée au rôle.',
            'effective_permissions' => $user->effectivePermissions(),
        ]);
    }
}
