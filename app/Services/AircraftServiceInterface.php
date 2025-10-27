<?php

namespace App\Services;

use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Collection;

interface AircraftServiceInterface
{
    public function getAll(): Collection;
    public function findByImmatriculation(string $immatriculation): ?Aircraft;
    public function findByOperator(int $operatorId): Collection;
    public function store(array $data): Aircraft;
    public function update(Aircraft $aircraft, array $data): Aircraft;
    public function delete(Aircraft $aircraft): void;
}
