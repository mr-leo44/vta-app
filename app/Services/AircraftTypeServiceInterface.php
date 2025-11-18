<?php

namespace App\Services;


use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AircraftTypeServiceInterface
{
    public function getAll(): Collection;
    public function getAllPaginated(): LengthAwarePaginator;
    public function find(string $query): LengthAwarePaginator;
    public function store(array $data): AircraftType;
    public function update(AircraftType $aircraftType, array $data): AircraftType;
    public function delete(AircraftType $aircraftType): void;
}