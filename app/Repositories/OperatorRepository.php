<?php

namespace App\Repositories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->buildSearchQuery($term)->latest()->paginate(10);
            
    }

    private function buildSearchQuery(string $term): Builder
{
    return Operator::where('name', 'like', "%{$term}%")
        ->orWhere('iata_code', 'like', "%{$term}%")
        ->orWhere('icao_code', 'like', "%{$term}%")
        ->orWhere('sigle', 'like', "%{$term}%");
    }
}
