<?php

namespace App\Services;

use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Enums\FlightTypeEnum;
use App\Models\Flight;
use App\Repositories\FlightRepository;
use App\Repositories\FlightStatisticRepository;
use Illuminate\Support\Facades\DB;

class FlightService
{
    public function __construct(
        private FlightRepository $flightRepo,
        private FlightStatisticRepository $flightStatRepo
    ) {}

    public function list(array $filters)
    {
        return $this->flightRepo->all($filters);
    }

    public function show(int $id): ?Flight
    {
        return $this->flightRepo->find($id);
    }

    public function store(array $data): Flight
    {
        return DB::transaction(function () use ($data) {
            $flight = $this->flightRepo->create([
                'flight_number' => $data['flight_number'],
                'operator_id' => $data['operator_id'],
                'aircraft_id' => $data['aircraft_id'],
                'flight_regime' => $data['flight_regime'] ?? FlightRegimeEnum::DOMESTIC->value,
                'flight_type' => $data['flight_type'] ?? FlightTypeEnum::REGULAR->value,
                'flight_nature' => $data['flight_nature'] ?? FlightNatureEnum::COMMERCIAL->value,
                'status' => $data['status'] ?? FlightStatusEnum::SCHEDULED->value,
                'departure' => $data['departure'],
                'arrival' => $data['arrival'],
                'departure_time' => $data['departure_time'],
                'arrival_time' => $data['arrival_time'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->flightStatRepo->create([
                'flight_id' => $flight->id,
                ...($data['statistics'] ?? [])
            ]);

            return $flight->load('statistic');
        });
    }

    public function update(Flight $flight, array $data): Flight
    {
        return DB::transaction(function () use ($flight, $data) {
            $flight = $this->flightRepo->update($flight, $data);

            if (isset($data['statistics'])) {
                $this->flightStatRepo->updateOrCreate(
                    ['flight_id' => $flight->id],
                    $data['statistics']
                );
            }

            return $flight->load('statistic');
        });
    }

    public function delete(Flight $flight): void
    {
        DB::transaction(function () use ($flight) {
            $this->flightStatRepo->deleteByFlight($flight->id);
            $this->flightRepo->delete($flight);
        });
    }
}
