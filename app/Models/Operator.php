<?php

namespace App\Models;

use App\Models\Flight;
use App\Models\Aircraft;
use App\Enums\FlightTypeEnum;
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
    ];

    protected $casts = [
        'flight_type' => FlightTypeEnum::class,
    ];

    public function flights()
    {
        return $this->hasMany(Flight::class);
    }

    public function aircrafts()
    {
        return $this->hasMany(Aircraft::class);
    }
}
