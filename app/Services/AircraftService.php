<?php

namespace App\Services;


use App\Models\Aircraft;
use App\Services\AircraftServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\AircraftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AircraftService implements AircraftServiceInterface
{
    public function __construct(protected AircraftRepositoryInterface $repository) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

     public function getAllPaginated(): LengthAwarePaginator
    {
        return $this->repository->allPaginated();
    }

    public function search(string $term): ?LengthAwarePaginator
    {
        return $this->repository->search($term);
    }
    
    public function store(array $data): Aircraft
    {
        return $this->repository->create($data);
    }
    public function update(Aircraft $aircraft, array $data): Aircraft
    {
        return $this->repository->update($aircraft, $data);
    }
    public function delete(Aircraft $aircraft): bool
    {
        return $this->repository->delete($aircraft);
    }
}
