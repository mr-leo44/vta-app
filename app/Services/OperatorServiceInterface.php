<?php

namespace App\Services;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;

interface OperatorServiceInterface
{
    public function getAll(): Collection;

    public function store(array $data): Operator;

    public function update(Operator $operator, array $data): Operator;

    public function delete(Operator $operator): bool;

    public function findByNameOrIata(string $term): ?Operator;
}
