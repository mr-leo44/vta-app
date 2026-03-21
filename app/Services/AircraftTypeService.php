<?php

namespace App\Services;

use App\Models\AircraftType;
use App\Repositories\AircraftTypeRepositoryInterface;
use App\Services\AircraftTypeServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AircraftTypeService implements AircraftTypeServiceInterface
{
    public function __construct(protected AircraftTypeRepositoryInterface $repository) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function getAllPaginated(): LengthAwarePaginator
    {
        return $this->repository->allPaginated();
    }

    public function find(string $query): LengthAwarePaginator
    {
        return $this->repository->find($query);
    }

    public function store(array $data): AircraftType
    {
        return $this->repository->create($data);
    }

    public function update(AircraftType $aircraftType, array $data): AircraftType
    {
        return $this->repository->update($aircraftType, $data);
    }

    public function delete(AircraftType $aircraftType): void
    {
        $this->repository->delete($aircraftType);
    }

    public function filter(array $filters): LengthAwarePaginator
    {
        return $this->repository->filter($filters);
    }
}