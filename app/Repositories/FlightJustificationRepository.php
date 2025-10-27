<?php

namespace App\Repositories;

use App\Models\FlightJustification;
use Illuminate\Database\Eloquent\Collection;

class FlightJustificationRepository implements FlightJustificationRepositoryInterface
{
    public function all(): Collection
    {
        return FlightJustification::all();
    }

    public function create(array $data): FlightJustification
    {
        return FlightJustification::create($data);
    }

    public function update(FlightJustification $justification, array $data): FlightJustification
    {
        $justification->update($data);
        return $justification;
    }

    public function delete(FlightJustification $justification): void
    {
        $justification->delete();
    }
}


