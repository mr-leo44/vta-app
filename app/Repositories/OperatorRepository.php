<?php

namespace App\Repositories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository pour la gestion des opérateurs.
 * Gère toutes les interactions avec la base de données pour le modèle Operator.
 */
class OperatorRepository implements OperatorRepositoryInterface
{
    /**
     * Récupère tous les exploitants paginés.
     *
     * @return Illuminate\\Pagination\\LengthAwarePaginator
     */
    public function allPaginated(): LengthAwarePaginator
    {
        return Operator::with('flights', 'aircrafts')->orderBy('name')->latest()->paginate(10);
    }

    /**
     * Récupère tous les exploitants sans pagination.
     *
     * @return Illuminate\\Pagination\\LengthAwarePaginator
     */
    public function all(): Collection
    {
        return Operator::with('flights', 'aircrafts')->orderBy('name')->latest()->get();
    }

    /**
     * Crée un nouvel opérateur.
     *
     * @param array $data
     * @return Operator
     */
    public function create(array $data): Operator
    {
        return Operator::create($data);
    }

    /**
     * Met à jour un opérateur existant.
     *
     * @param Operator $operator
     * @param array $data
     * @return Operator
     */
    public function update(Operator $operator, array $data): Operator
    {
        $operator->update($data);
        return $operator;
    }

    /**
     * Supprime un opérateur.
     *
     * @param Operator $operator
     * @return bool|null
     */
    public function delete(Operator $operator): bool
    {
        return $operator->delete();
    }

    /**
     * Recherche des opérateurs par nom ou code IATA.
     *
     * @param string $query
     * @return LengthAwarePaginator
     */
    public function findByNameOrIata(string $term): ?LengthAwarePaginator
    {
        return Operator::where('name', 'like', "%$term%")
            ->orWhere('iata_code', 'like', "%$term%")
            ->orWhere('icao_code', 'like', "%$term%")
            ->orWhere('sigle', 'like', "%$term%")
            ->latest()->paginate(10);
    }

    /**
     * Filter operators with multiple criteria and sorting.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function filter(array $filters): LengthAwarePaginator
    {
        $query = Operator::with('flights', 'aircrafts');

        // Search in name, IATA, ICAO, sigle
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('iata_code', 'like', "%$search%")
                    ->orWhere('icao_code', 'like', "%$search%")
                    ->orWhere('sigle', 'like', "%$search%");
            });
        }

        // Filter by country
        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        // Filter by flight type
        if (!empty($filters['flight_type'])) {
            $query->where('flight_type', $filters['flight_type']);
        }

        // Filter by flight regime
        if (!empty($filters['flight_regime'])) {
            $query->where('flight_regime', $filters['flight_regime']);
        }

        // Filter by flight nature
        if (!empty($filters['flight_nature'])) {
            $query->where('flight_nature', $filters['flight_nature']);
        }

        // Filter by operators with flights
        if ($filters['with_flights'] !== null && $filters['with_flights'] !== '') {
            if ($filters['with_flights']) {
                $query->has('flights');
            } else {
                $query->doesntHave('flights');
            }
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
