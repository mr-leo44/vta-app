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
            // immatriculation number of the aircraft
            'immatriculation' => $this->immatriculation,
            // Maximum take-off weight of the aircraft in kilograms
            'pmad' => $this->pmad,
            // Whether the aircraft is currently in service
            'in_activity' => $this->in_activity,
            // Type of the aircraft
            'type' => $this->whenLoaded('type', function () {
                return [
                    // Unique identifier for the aircraft type
                    'id' => $this->type->id,
                    // Name of the aircraft type
                    'name' => $this->type->name,
                    // ICAO code of the aircraft type, if applicable
                    'sigle' => $this->type->sigle,
                    'default_pmad' => $this->type->default_pmad,
                ];
            }),
            // Operator of the aircraft
            'operator' => $this->whenLoaded('operator', function () {
                return [
                    // Unique identifier for the operator
                    'id' => $this->operator->id,
                    // Name of the operator
                    'name' => $this->operator->name,
                    // Sigle of the operator
                    'sigle' => $this->operator->sigle,
                    // IATA code of the operator, if applicable
                    'iata_code' => $this->operator->iata_code ?? null, 
                   // ICAO code of the operator, if applicable
                    'icao_code' => $this->operator->qicao_code ?? null,
                ];
            }),
            // Flights of the aircraft
            'flights' => $this->whenLoaded('flights'),
            // Timestamp for when the aircraft was created
            'created_at' => $this->created_at,
            // Timestamp for when the aircraft was last updated
            'updated_at' => $this->updated_at,
        ];
    }
}
