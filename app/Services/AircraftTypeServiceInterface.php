<?php

namespace App\Services;


use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Collection;

interface AircraftTypeServiceInterface
{
    public function getAll(): Collection;
    public function find(string $query): ?AircraftType;
    public function store(array $data): AircraftType;
    public function update(AircraftType $aircraftType, array $data): AircraftType;
    public function delete(AircraftType $aircraftType): void;
}