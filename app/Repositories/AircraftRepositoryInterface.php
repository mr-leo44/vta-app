<?php

namespace App\Repositories;

use App\Models\Aircraft;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AircraftRepositoryInterface
{
    public function all(): LengthAwarePaginator;
    public function search(string $immatriculation): ?LengthAwarePaginator;
    public function create(array $data): Aircraft;
    public function update(Aircraft $aircraft, array $data): Aircraft;
    public function delete(Aircraft $aircraft): bool;
}
