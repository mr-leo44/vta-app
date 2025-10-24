<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OperatorResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iata_code' => $this->iata_code,
            'icao_code' => $this->icao_code,
            'country' => $this->country,
            'flight_regime' => [
                'value' => $this->flight_regime->value,
                'label' => $this->flight_regime->label(),
            ],
            'flight_type' => [
                'value' => $this->flight_type->value,
                'label' => $this->flight_type->label(),
            ],
            'flight_nature' => [
                'value' => $this->flight_nature->value,
                'label' => $this->flight_nature->label(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
