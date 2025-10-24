<?php

namespace App\Services;

use App\Models\Operator;
use App\Repositories\OperatorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OperatorService implements OperatorServiceInterface
{
    public function __construct(
        protected OperatorRepositoryInterface $repository
    ) {}

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

    public function findByNameOrIata(string $term): ?Operator
    {
        return $this->repository->findByNameOrIata($term);
    }
}
