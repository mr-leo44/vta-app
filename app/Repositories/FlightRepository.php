<?php

namespace App\Repositories;

use Illuminate\Support\Carbon;
use App\Models\Flight;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\FlightRepositoryInterface;

class FlightRepository implements FlightRepositoryInterface
{
    public function all(array $filters = [])
    {
        return Flight::with('statistic')
            ->when($filters['operator_id'] ?? null, fn(Builder $q, $v) => $q->where('operator_id', $v))
            ->when($filters['aircraft_id'] ?? null, fn(Builder $q, $v) => $q->where('aircraft_id', $v))
            ->when($filters['flight_regime'] ?? null, fn(Builder $q, $v) => $q->where('flight_regime', $v))
            ->when($filters['flight_type'] ?? null, fn(Builder $q, $v) => $q->where('flight_type', $v))
            ->when($filters['flight_nature'] ?? null, fn(Builder $q, $v) => $q->where('flight_nature', $v))
            ->when($filters['status'] ?? null, fn(Builder $q, $v) => $q->where('status', $v))
            ->when($filters['departure_iata'] ?? null, fn(Builder $q, $iata) => $q->whereJsonContains('departure->iata', $iata))
            ->when($filters['arrival_iata'] ?? null, fn(Builder $q, $iata) => $q->whereJsonContains('arrival->iata', $iata))
            ->when($filters['date_from'] ?? null, fn(Builder $q, $date) => $q->whereDate('departure_time', '>=', Carbon::parse($date)))
            ->when($filters['date_to'] ?? null, fn(Builder $q, $date) => $q->whereDate('departure_time', '<=', Carbon::parse($date)))
            ->orderBy('departure_time', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Flight
    {
        return Flight::create($data);
    }

    public function find(int $id): ?Flight
    {
        return Flight::with('statistic')->find($id);
    }

    public function update(Flight $flight, array $data): Flight
    {
        $flight->update($data);
        return $flight->refresh();
    }

    public function delete(Flight $flight): void
    {
        $flight->delete();
    }
}
