<?php

namespace App\Repositories;

use App\Models\Flight;
use Illuminate\Support\Collection;

interface FlightRepositoryInterface
{
    public function all(array $filters = []);
    public function create(array $data): Flight;
    public function find(int $id): ?Flight;
    public function update(Flight $flight, array $data): Flight;
    public function delete(Flight $flight): void;
}
