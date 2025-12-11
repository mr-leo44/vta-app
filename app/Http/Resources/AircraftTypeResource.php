<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AircraftTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Unique identifier for the aircraft type
            'id' => $this->id,
            // Name of the aircraft type
            'name' => $this->name,
            // ICAO code of the aircraft type, if applicable
            'sigle' => $this->sigle,
            'default_pmad' => $this->default_pmad,
            // aircrafts
            'aircrafts' => $this->whenLoaded('aircrafts'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
