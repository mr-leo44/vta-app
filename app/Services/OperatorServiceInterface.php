<?php

namespace App\Services;

use App\Models\Operator;
use Illuminate\Pagination\LengthAwarePaginator;

interface OperatorServiceInterface
{
    public function getAll(): LengthAwarePaginator;

    public function store(array $data): Operator;

    public function update(Operator $operator, array $data): Operator;

    public function delete(Operator $operator): bool;

    public function findByNameOrIata(string $term): ?LengthAwarePaginator;
}
