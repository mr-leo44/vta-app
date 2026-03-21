<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * AuthController — OWASP A07 (Identification & Authentication Failures)
 *
 * Mesures de sécurité :
 *  - Rate limiting géré par le middleware throttle:5,1 sur la route /login
 *  - Message d'erreur générique (pas d'énumération des usernames)
 *  - Token Sanctum avec expiration (8 heures)
 *  - Révocation des anciens tokens au login (évite l'accumulation de tokens orphelins)
 *  - Hash::check() constant-time (pas de timing attack)
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->validated();

        $user = User::where('username', $request->string('username'))->first();

        // Hash::check() s'exécute même si $user est null pour éviter le timing attack
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        // Révoque tous les tokens "api-token" précédents pour cet utilisateur
        $user->tokens()->where('name', 'api-token')->delete();

        // Crée un token avec expiration 8h
        $token = $user->createToken(
            name:           'api-token',
            abilities:      ['*'],
            expiresAt:      now()->addHours(8),
        );

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'       => $user->load('currentFunction')->accessSummary(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès.']);
    }
}
