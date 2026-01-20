<?php

namespace App\Repositories;

use Illuminate\Support\Carbon;
use App\Models\Flight;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\FlightRepositoryInterface;

class FlightRepository implements FlightRepositoryInterface
{

    public function all(): Collection
    {
        return Flight::with(['statistic', 'operator', 'aircraft'])
            ->latest()
            ->get();
    }
    public function allPaginated(array $filters = [])
    {
        return Flight::with(['statistic', 'operator', 'aircraft'])
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
        return Flight::with(['statistic', 'operator', 'aircraft'])->find($id);
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

    public function filter(array $filters)
    {
        $query = Flight::with(['statistic', 'operator', 'aircraft']);

        // Search in flight_number, departure airport, arrival airport
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('flight_number', 'like', "%$search%")
                    ->orWhereJsonContains('departure->iata', $search)
                    ->orWhereJsonContains('arrival->iata', $search);
            });
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by flight regime
        if (!empty($filters['flight_regime'])) {
            $query->where('flight_regime', $filters['flight_regime']);
        }

        // Filter by flight type
        if (!empty($filters['flight_type'])) {
            $query->where('flight_type', $filters['flight_type']);
        }

        // Filter by operator
        if (!empty($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }

        // Filter by aircraft
        if (!empty($filters['aircraft_id'])) {
            $query->where('aircraft_id', $filters['aircraft_id']);
        }

        // Filter by departure date range
        if (!empty($filters['departure_date_from'])) {
            $query->whereDate('departure_time', '>=', Carbon::parse($filters['departure_date_from']));
        }
        if (!empty($filters['departure_date_to'])) {
            $query->whereDate('departure_time', '<=', Carbon::parse($filters['departure_date_to']));
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'departure_time:desc';
        [$column, $direction] = explode(':', $sort);
        $query->orderBy($column, strtoupper($direction));

        // Paginate
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
