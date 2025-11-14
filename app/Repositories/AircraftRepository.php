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
        return Aircraft::with(['operator', 'type', 'flights'])->orderBy('name')->latest()->get();
    }

    public function allPaginated(): LengthAwarePaginator
    {
        return Aircraft::with(['operator', 'type', 'flights'])->orderBy('name')->latest()->paginate(10);
    }

    public function search(string $term): ?LengthAwarePaginator
    {
        return Aircraft::with(['operator', 'type'])
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
}
