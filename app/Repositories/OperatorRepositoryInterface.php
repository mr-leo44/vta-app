<?php

namespace App\Repositories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OperatorRepositoryInterface
{
    public function allPaginated(): LengthAwarePaginator;
    public function all(): Collection;
    public function create(array $data): Operator;
    public function update(Operator $operator, array $data): Operator;
    public function delete(Operator $operator): bool;
    public function findByNameOrIata(string $term): ?LengthAwarePaginator;

    /**
     * Filter operators with multiple criteria.
     * 
     * @param array $filters {
     *     search?: string,           // Search in name/IATA/ICAO
     *     country?: string,          // Filter by country
     *     flight_type?: string,      // Filter by flight type
     *     flight_regime?: string,    // Filter by flight regime
     *     flight_nature?: string,    // Filter by flight nature
     *     sort?: string,             // Sort: name:asc, name:desc, created_at:asc, created_at:desc, updated_at:desc
     *     per_page?: int,            // Items per page
     * }
     * @return LengthAwarePaginator
     */
    public function filter(array $filters): LengthAwarePaginator;
}
