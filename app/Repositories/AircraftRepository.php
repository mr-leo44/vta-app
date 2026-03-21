<?php

namespace App\Repositories;

use App\Models\Aircraft;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\AircraftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AircraftRepository implements AircraftRepositoryInterface
{
    public function all(): Collection
    {
        return Aircraft::with(['operator', 'type', 'flights'])->orderBy('immatriculation')->latest()->get();
    }

    public function allPaginated(): LengthAwarePaginator
    {
        return Aircraft::with(['operator', 'type', 'flights'])->orderBy('immatriculation')->latest()->paginate(10);
    }

    public function search(string $term): ?LengthAwarePaginator
    {
        return Aircraft::with(['operator', 'type', 'flights'])
            ->where('immatriculation', 'like', "%$term%")
            ->orWhereHas('operator', function ($query) use ($term) {
                $query->where('name', 'like', "%$term%")
                    ->orWhere('sigle', 'like', "%$term%");
            })
            ->orWhereHas('type', function ($query) use ($term) {
                $query->where('name', 'like', "%$term%");
            })
            ->latest()->paginate(10);
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

    public function delete(Aircraft $aircraft): bool
    {
        return $aircraft->delete();
    }

    public function filter(array $filters): LengthAwarePaginator
    {
        $query = Aircraft::with(['operator', 'type', 'flights']);

        // Search in immatriculation
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('immatriculation', 'like', "%$search%");
        }

        // Filter by operator
        if (!empty($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }

        // Filter by aircraft type
        if (!empty($filters['aircraft_type_id'])) {
            $query->where('aircraft_type_id', $filters['aircraft_type_id']);
        }

        // Filter by PMAD (range/interval)
        if (!empty($filters['pmad_from']) || !empty($filters['pmad_to'])) {
            $pmadFrom = !empty($filters['pmad_from']) ? $filters['pmad_from'] : 0;
            $pmadTo = !empty($filters['pmad_to']) ? $filters['pmad_to'] : PHP_INT_MAX;
            $query->whereBetween('pmad', [$pmadFrom, $pmadTo]);
        }

        // Filter by in_activity
        if ($filters['in_activity'] !== null && $filters['in_activity'] !== '') {
            $query->where('in_activity', (bool) $filters['in_activity']);
        }

        // Filter by with_flights (has flights or not)
        if ($filters['with_flights'] !== null && $filters['with_flights'] !== '') {
            if ($filters['with_flights']) {
                $query->has('flights');
            } else {
                $query->doesntHave('flights');
            }
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'immatriculation:asc';
        [$column, $direction] = explode(':', $sort);
        $query->orderBy($column, strtoupper($direction));

        // Paginate
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
