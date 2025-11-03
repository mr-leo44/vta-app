<?php

namespace App\Services;

use App\Models\AircraftType;
use App\Repositories\AircraftTypeRepositoryInterface;
use App\Services\AircraftTypeServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class AircraftTypeService implements AircraftTypeServiceInterface
{
    public function __construct(protected AircraftTypeRepositoryInterface $repository) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function find(string $query): ?AircraftType
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
}

