<?php

namespace App\Repositories;

use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AircraftTypeRepositoryInterface
{
    public function all(): Collection;
    public function allPaginated(): LengthAwarePaginator;
    public function find(string $query): LengthAwarePaginator;
    public function create(array $data): AircraftType;
    public function update(AircraftType $aircraftType, array $data): AircraftType;
    public function delete(AircraftType $aircraftType): void;
    public function filter(array $filters): LengthAwarePaginator;
}