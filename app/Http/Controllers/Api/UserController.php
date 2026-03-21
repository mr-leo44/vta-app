<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserFunction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignFunctionRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
