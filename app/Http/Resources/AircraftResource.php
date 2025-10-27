<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AircraftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            // Unique identifier for the aircraft
            'id' => $this->id,
            // Registration number of the aircraft
            'immatriculation' => $this->registration,
            // Maximum take-off weight of the aircraft in kilograms
            'pmad' => $this->pmad,
            // Whether the aircraft is currently in service
            'in_activity' => $this->in_activity,
            // Type of the aircraft
            'aircraft_type' => $this->whenLoaded('type', function () {
                return [
                    // Unique identifier for the aircraft type
                    'id' => $this->type->id,
                    // Name of the aircraft type
                    'name' => $this->type->name,
                    // ICAO code of the aircraft type, if applicable
                    'sigle' => $this->type->sigle,
                ];
            }),
            // Operator of the aircraft
            'operator' => $this->whenLoaded('operator', function () {
                return [
                    // Unique identifier for the operator
                    'id' => $this->operator->id,
                    // Name of the operator
                    'name' => $this->operator->name,
                    // IATA code of the operator, if applicable
                    'iata_code' => $this->operator->iata_code ?? null,
                ];
            }),
            // Timestamp for when the aircraft was created
            'created_at' => $this->created_at,
            // Timestamp for when the aircraft was last updated
            'updated_at' => $this->updated_at,
        ];
    }
}
