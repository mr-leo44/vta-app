<?php

namespace App\Services;

use App\Repositories\UserRepositoryInterface;
use Illuminate\Hashing\HashManager;
use Laravel\Sanctum\PersonalAccessToken;
use App\Helpers\ApiResponse;
use App\Models\User;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected HashManager $hash
    ) {}

    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->users->findByUsername($username);
        if (! $user) {
            return null;
        }

        if (! $this->hash->check($password, $user->password)) {
            return null;
        }

        // create sanctum token
        $token = $user->createToken('vta-app-auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): bool
    {
        return (bool) $user->tokens()->delete();
    }
}
