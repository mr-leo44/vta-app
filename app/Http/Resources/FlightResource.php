<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FlightResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'flight_number' => $this->flight_number,
            'operator' => $this->operator ? [
                // Name of the operator
                'name' => $this->operator->name,
                // ICAO sigle of the operator, if applicable
                'sigle' => $this->operator->sigle,
            ] : null,
            'aircraft' => $this->aircraft?->immatriculation,
            'flight_regime' => $this->flight_regime,
            'flight_type' => $this->flight_type,
            'flight_nature' => $this->flight_nature,
            'status' => $this->status,
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'departure_time' => $this->departure_time,
            'arrival_time' => $this->arrival_time,
            'remarks' => $this->remarks,
            'statistics' => $this->whenLoaded('statistic'),
        ];
    }
}
