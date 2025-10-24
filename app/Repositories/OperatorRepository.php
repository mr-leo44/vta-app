<?php

namespace App\Repositories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository pour la gestion des opérateurs.
 * Gère toutes les interactions avec la base de données pour le modèle Operator.
 */
class OperatorRepository implements OperatorRepositoryInterface
{
    /**
     * Récupère tous les exploitants.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return Operator::orderBy('name')->get();
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
     * @return Collection
     */
    public function findByNameOrIata(string $term): ?Operator
    {
        return Operator::where('name', 'like', "%$term%")
            ->orWhere('iata_code', 'like', "%$term%")
            ->first();
    }
}
