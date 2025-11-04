<?php

namespace App\Services;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\OperatorRepositoryInterface;

class OperatorService implements OperatorServiceInterface
{
    public function __construct(
        protected OperatorRepositoryInterface $repository
    ) {}

    public function getAll(): LengthAwarePaginator
    {
        return $this->repository->all();
    }

    public function store(array $data): Operator
    {
        return $this->repository->create($data);
    }

    public function update(Operator $operator, array $data): Operator
    {
        return $this->repository->update($operator, $data);
    }

    public function delete(Operator $operator): bool
    {
        return $this->repository->delete($operator);
    }

    public function findByNameOrIata(string $term): ?Collection
    {
        return $this->repository->findByNameOrIata($term);
    }
}
