<?php

namespace App\Repositories;

use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Collection;

class AircraftTypeRepository implements AircraftTypeRepositoryInterface
{
    public function all(): Collection
    {
        return AircraftType::latest()->get();
    }

    public function find(string $query): ?AircraftType
    {
        return AircraftType::where('name', $query)->orWhere('sigle', $query)->first();
    }

    public function create(array $data): AircraftType
    {
        return AircraftType::create($data);
    }

    public function update(AircraftType $aircraftType, array $data): AircraftType
    {
        $aircraftType->update($data);
        return $aircraftType;
    }
    public function delete(AircraftType $aircraftType): void
    {
        $aircraftType->delete();
    }
}