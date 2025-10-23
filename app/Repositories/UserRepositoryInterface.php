<?php

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?User;
    public function findByName(string $name): ?User;
    public function findById(int $id): ?User;
}
