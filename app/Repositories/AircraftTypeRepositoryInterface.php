<?php

namespace App\Repositories;

use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Collection;

interface AircraftTypeRepositoryInterface
{
    public function all(): Collection;
    public function find(string $query): ?AircraftType;
    public function create(array $data): AircraftType;
    public function update(AircraftType $aircraftType, array $data): AircraftType;
    public function delete(AircraftType $aircraftType): void;
}