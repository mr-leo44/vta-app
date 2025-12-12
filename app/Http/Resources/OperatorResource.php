<?php

namespace App\Http\Resources;

use App\Http\Resources\FlightResource;
use App\Http\Resources\AircraftResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sigle' => $this->sigle,
            'iata_code' => $this->iata_code,
            'icao_code' => $this->icao_code,
            'country' => $this->country,
            'flight_type' => [
                'value' => $this->flight_type->value,
                'label' => $this->flight_type->label(),
            ],
            'flights' => FlightResource::collection($this->whenLoaded('flights')),
            'aircrafts' => AircraftResource::collection($this->whenLoaded('aircrafts', function() {
                return $this->aircrafts()->with('type')->get();
            })),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
