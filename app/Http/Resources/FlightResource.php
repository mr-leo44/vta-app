<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FlightResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            // Unique identifier of the flight
            'id' => $this->id,

            // Flight number
            'flight_number' => $this->flight_number,

            // Operator of the flight
            'operator' => $this->operator ? [
                'id' => $this->operator->id,
                // Name of the operator
                'name' => $this->operator->name,
                // ICAO sigle of the operator, if applicable
                'sigle' => $this->operator->sigle,
            ] : null,

            // Aircraft of the flight
            'aircraft' => $this->aircraft ? [
                'id' => $this->aircraft->id,
                // Immatriculation of the aircraft
                'immatriculation' => $this->aircraft->immatriculation,
                // Type of the aircraft
                'type' => $this->aircraft->type?->name
            ] : null,

            // Regime of the flight
            'flight_regime' => $this->flight_regime,

            // Type of the flight
            'flight_type' => $this->flight_type,

            // Nature of the flight
            'flight_nature' => $this->flight_nature,

            // Status of the flight
            'status' => $this->status,

            // Departure location of the flight (normalized to from/to)
            'departure' => $this->formatLocation($this->departure),

            // Arrival location of the flight (normalized to from/to)
            'arrival' => $this->formatLocation($this->arrival),

            // Departure time of the flight
            'departure_time' => $this->departure_time,

            // Arrival time of the flight
            'arrival_time' => $this->arrival_time,

            // Remarks of the flight
            'remarks' => $this->remarks,

            // Statistics of the flight
            'statistics' => $this->whenLoaded('statistic'),

            // Creation date of the flight
            'created_at' => $this->created_at,

            // Last update date of the flight
            'updated_at' => $this->updated_at,
        ];
    }

    private function formatLocation($loc)
    {
        if (empty($loc)) return null;

        // already new shape
        if (isset($loc['from']) || isset($loc['to'])) {
            $from = $loc['from'] ?? ['iata' => $loc['iata'] ?? null, 'name' => $loc['name'] ?? null];
            $to = $loc['to'] ?? ['iata' => $loc['iata'] ?? null, 'name' => $loc['name'] ?? null];
            return ['from' => $from, 'to' => $to];
        }

        // legacy shape: {iata,name}
        if (is_array($loc) && (isset($loc['iata']) || isset($loc['name']))) {
            $pair = ['iata' => $loc['iata'] ?? null, 'name' => $loc['name'] ?? null];
            return ['from' => $pair, 'to' => $pair];
        }

        // fallback: return as-is
        return $loc;
    }
}
