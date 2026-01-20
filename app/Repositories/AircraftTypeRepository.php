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

    public function filter(array $filters): LengthAwarePaginator
    {
        $query = AircraftType::with('aircrafts');

        // Search in name and sigle
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('sigle', 'like', "%$search%");
            });
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'name:asc';
        [$column, $direction] = explode(':', $sort);
        $query->orderBy($column, strtoupper($direction));

        // Paginate
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
