<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use App\Models\Aircraft;
use App\Repositories\AircraftRepositoryInterface;

class AircraftRepository implements AircraftRepositoryInterface
{
    public function all(): Collection
    {
        return Aircraft::with(['operator', 'type'])->latest()->get();
    }

    public function findByImmatriculation(string $immatriculation): ?Aircraft
    {
        return Aircraft::with(['operator', 'type'])->where('immatriculation', $immatriculation)->first();
    }

    public function findByOperator(int $operatorId): Collection
    {
        return Aircraft::with(['operator', 'type'])->where('operator_id', $operatorId)->get();
    }

    public function create(array $data): Aircraft
    {
        return Aircraft::create($data);
    }

    public function update(Aircraft $aircraft, array $data): Aircraft
    {
        $aircraft->update($data);
        return $aircraft;
    }

    public function delete(Aircraft $aircraft): void
    {
        $aircraft->delete();
    }
}
