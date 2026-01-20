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

    public function getAllPaginated(): LengthAwarePaginator
    {
        return $this->repository->allPaginated();
    }

    public function getAll(): Collection
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

    public function findByNameOrIata(string $term): ?LengthAwarePaginator
    {
        return $this->repository->findByNameOrIata($term);
    }

    public function filter(array $filters): LengthAwarePaginator
    {
        return $this->repository->filter($filters);
    }
}