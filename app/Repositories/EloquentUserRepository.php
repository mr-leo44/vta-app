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
        return User::where('name', $name)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}
