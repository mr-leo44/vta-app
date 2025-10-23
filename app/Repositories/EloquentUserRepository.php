<?php

namespace App\Repositories;

use App\Models\User;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function findByName(string $name): ?User
    {
        // Alias for backward compatibility / alternative naming
        return $this->findByUsername($name);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}
