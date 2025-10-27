<?php

namespace App\Repositories;

use App\Models\Aircraft;
use Illuminate\Support\Collection;
interface AircraftRepositoryInterface
{
    public function all(): Collection;
    public function findByImmatriculation(string $immatriculation): ?Aircraft;
    public function findByOperator(int $operatorId): Collection;
    public function create(array $data): Aircraft;
    public function update(Aircraft $aircraft, array $data): Aircraft;
    public function delete(Aircraft $aircraft): void;
}
