<?php

namespace App\Models;

use App\Enums\{FlightTypeEnum, FlightNatureEnum};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sigle',
        'iata_code',
        'icao_code',
        'country',
        'flight_type',
        'flight_nature',
    ];

    protected $casts = [
        'flight_type' => FlightTypeEnum::class,
        'flight_nature' => FlightNatureEnum::class,
    ];
}
