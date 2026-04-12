<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserFunction;
use App\Helpers\PasswordGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignFunctionRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

/**
 * UserController
 *
 * Toutes les actions vérifient les permissions via Policy (UserPolicy).
 * Le constructeur lie automatiquement le modèle User à UserPolicy.
 */
class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    /** GET /api/users — liste paginée (admin seulement) */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with('currentFunction')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function all(Request $request): AnonymousResourceCollection
    {
        $users = User::with('currentFunction')
            ->latest()->get();

        return UserResource::collection($users);
    }

    /** POST /api/users — crée un utilisateur */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json(new UserResource($user), 201);
    }

    /** PUT /api/users/{user} — met à jour (ne touche pas au rôle) */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user->fresh('currentFunction'));
    }

    /** DELETE /api/users/{user} */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /**
     * POST /api/users/{user}/assign-function
     *
     * Assigne une fonction → le rôle Spatie est synchronisé automatiquement.
     * Seul un admin (user.assignFunction) peut appeler cette action.
     */
    public function assignFunction(AssignFunctionRequest $request, User $user): JsonResponse
    {
        $this->authorize('assignFunction', $user);

        $function = UserFunction::from($request->validated('function'));
        $history  = $user->assignFunction($function, $request->validated('start_date'));

        return response()->json([
            'message'    => 'Fonction assignée avec succès.',
            'function'   => $history->function,
            'role'       => $user->getRoleNames()->first(),
            'start_date' => $history->start_date->toDateString(),
        ]);
    }

    /**
     * GET /api/user — profil de l'utilisateur connecté.
     *
     * Retourne les permissions pour que Nuxt puisse initialiser le store Pinia.
     * Pas de Policy ici : chaque utilisateur accède à son propre profil.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('currentFunction');

        return response()->json($user->accessSummary());
    }

    /**
     * PUT /api/profile/update — met à jour le profil de l'utilisateur connecté.
     *
     * Permet de changer name et username.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $this->authorize('updateProfile');

        $user = $request->user();
        $validated = $request->validated();

        $user->update($validated);

        AuditLog::record(
            event:     'profile_updated',
            subject:   $user,
            newValues: $validated,
        );

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * POST /api/profile/change-password — change le mot de passe de l'utilisateur connecté.
     *
     * Nécessite : current_password, password, password_confirmation
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authorize('changePassword');

        $user = $request->user();
        $validated = $request->validated();

        // Vérifier le mot de passe actuel
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => $validated['password'],
        ]);

        AuditLog::record(
            event:   'password_changed',
            subject: $user,
            newValues: [],
        );

        // Révoquer tous les tokens existants pour forcer une reconnexion
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Mot de passe changé. Reconnecter-vous.',
        ]);
    }

    /**
     * POST /api/users/{user}/reset-password — réinitialise le mot de passe d'un utilisateur.
     *
     * Admin uniquement (user.resetPassword).
     * Génère un password aléatoire sûr et le retourne UNE SEULE FOIS.
     * Le nouveau password ne sera plus accessible après cette réponse.
     */
    public function resetPassword(User $user): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        // Générer un password sûr
        $newPassword = PasswordGenerator::generate(16);

        // Mettre à jour l'utilisateur
        $user->update([
            'password' => $newPassword,
        ]);

        // Révoquer tous les tokens de cet utilisateur
        $user->tokens()->delete();

        // Enregistrer l'action dans l'audit
        AuditLog::record(
            event:     'password_reset',
            subject:   $user,
            newValues: [
                'reset_by' => auth()->user()->name,
            ],
        );

        // IMPORTANT : Retourner le password UNE SEULE FOIS
        // Une fois cette réponse fermée, le password n'est plus accessible nulle part
        return response()->json([
            'message'     => 'Mot de passe réinitialisé avec succès.',
            'password'    => $newPassword,
            'warning'     => 'Ce mot de passe ne sera visible qu\'une seule fois. Copiez-le maintenant.',
            'user'        => [
                'id'   => $user->id,
                'name' => $user->name,
            ],
        ]);
    }
}
