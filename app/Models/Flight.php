<?php

namespace App\Models;

use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Flight extends Model
{
    use HasFactory;

    protected $fillable = [
        'flight_number',
        'operator_id',
        'aircraft_id',
        'flight_regime',
        'flight_type',
        'flight_nature',
        'status',
        'departure',
        'arrival',
        'departure_time',
        'arrival_time',
        'remarks',
    ];

    protected $casts = [
        'departure' => 'array',
        'arrival' => 'array',
        'flight_nature' => FlightNatureEnum::class,
        'flight_type' => FlightTypeEnum::class,
        'flight_regime' => FlightRegimeEnum::class,
        'status' => FlightStatusEnum::class,
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
    ];

    // relations
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function aircraft()
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function statistic()
    {
        return $this->hasOne(FlightStatistic::class);
    }
}
