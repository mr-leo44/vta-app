<?php

namespace App\Repositories;

use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AircraftTypeRepository implements AircraftTypeRepositoryInterface
{
    public function all(): Collection
    {
        return AircraftType::with('aircrafts')->orderBy('name')->latest()->get();
    }

    public function allPaginated(): LengthAwarePaginator
    {
        return AircraftType::with('aircrafts')->orderBy('name')->latest()->paginate();
    }

    public function find(string $query): LengthAwarePaginator
    {
        return AircraftType::with('aircrafts')->where('name', 'like', "%$query%")->orWhere('sigle', 'like', "%$query%")->latest()->paginate();
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
