<?php

namespace App\Models;

use App\Enums\{FlightRegimeEnum, FlightTypeEnum, FlightNatureEnum};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iata_code',
        'icao_code',
        'country',
        'flight_regime',
        'flight_type',
        'flight_nature',
    ];

    protected $casts = [
        'flight_regime' => FlightRegimeEnum::class,
        'flight_type' => FlightTypeEnum::class,
        'flight_nature' => FlightNatureEnum::class,
    ];
}
