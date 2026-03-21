<?php

namespace App\Repositories;

use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AircraftRepositoryInterface
{
    public function all(): Collection;
    public function allPaginated(): LengthAwarePaginator;
    public function search(string $immatriculation): ?LengthAwarePaginator;
    public function create(array $data): Aircraft;
    public function update(Aircraft $aircraft, array $data): Aircraft;
    public function delete(Aircraft $aircraft): bool;
    public function filter(array $filters): LengthAwarePaginator;
}
