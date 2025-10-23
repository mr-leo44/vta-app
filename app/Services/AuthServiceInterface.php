<?php

namespace App\Services;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Attempt to authenticate a user by username and password.
     * Returns the user and token on success, null on failure.
     *
     * @return array{user: User, token: string}|null
     */
    public function authenticate(string $username, string $password): ?array;

    /**
     * Logout the given user by revoking their current access token.
     * Returns true on success, false otherwise.
     */
    public function logout(\App\Models\User $user): bool;
}
