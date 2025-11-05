<?php

namespace App\Services;

use App\Models\Aircraft;
use Illuminate\Pagination\LengthAwarePaginator;

interface AircraftServiceInterface
{
    public function getAll(): LengthAwarePaginator;
    public function search(string $immatriculation): ?LengthAwarePaginator;
    public function store(array $data): Aircraft;
    public function update(Aircraft $aircraft, array $data): Aircraft;
    public function delete(Aircraft $aircraft): bool;
}
