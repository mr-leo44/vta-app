<?php

namespace App\Services;


use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Collection;
use App\Services\AircraftServiceInterface;
use App\Repositories\AircraftRepositoryInterface;

class AircraftService implements AircraftServiceInterface
{
    public function __construct(protected AircraftRepositoryInterface $repository) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function findByImmatriculation(string $immatriculation): ?Aircraft
    {
        return $this->repository->findByImmatriculation($immatriculation);
    }

    /**
     * Returns all aircrafts that belong to the given operator.
     *
     * @param int $operatorId The ID of the operator.
     * @return Collection The aircrafts belonging to the operator.
     */
    public function findByOperator(int $operatorId): Collection
    {
        return $this->repository->findByOperator($operatorId);
    }
    
    public function store(array $data): Aircraft
    {
        return $this->repository->create($data);
    }
    public function update(Aircraft $aircraft, array $data): Aircraft
    {
        return $this->repository->update($aircraft, $data);
    }
    public function delete(Aircraft $aircraft): void
    {
        $this->repository->delete($aircraft);
    }
}
