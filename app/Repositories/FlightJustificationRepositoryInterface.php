<?php

namespace App\Repositories;

use App\Models\FlightJustification;
use Illuminate\Database\Eloquent\Collection;


interface FlightJustificationRepositoryInterface
{
    public function all(): Collection;
    public function create(array $data): FlightJustification;
    public function update(FlightJustification $justification, array $data): FlightJustification;
    public function delete(FlightJustification $justification): void;
}