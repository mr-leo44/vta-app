<?php

namespace App\Repositories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
interface OperatorRepositoryInterface
{
    public function all(): LengthAwarePaginator;
    public function create(array $data): Operator;

    public function update(Operator $operator, array $data): Operator;

    public function delete(Operator $operator): bool;

    public function findByNameOrIata(string $term): ?Collection;
}
