<?php

namespace App\Services;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OperatorServiceInterface
{
    public function getAllPaginated(): LengthAwarePaginator;
    
    public function getAll(): Collection;

    public function store(array $data): Operator;

    public function update(Operator $operator, array $data): Operator;

    public function delete(Operator $operator): bool;

    public function findByNameOrIata(string $term): ?LengthAwarePaginator;
}
