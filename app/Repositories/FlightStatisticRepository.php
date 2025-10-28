<?php

namespace App\Repositories;

use App\Models\FlightStatistic;

class FlightStatisticRepository
{
    public function create(array $data): FlightStatistic
    {
        return FlightStatistic::create($data);
    }

    public function updateOrCreate(array $conditions, array $data): FlightStatistic
    {
        return FlightStatistic::updateOrCreate($conditions, $data);
    }

    public function deleteByFlight(int $flightId): void
    {
        FlightStatistic::where('flight_id', $flightId)->delete();
    }
}
