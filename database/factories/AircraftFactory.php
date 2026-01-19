<?php

namespace Database\Factories;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

class AircraftFactory extends Factory
{
    protected $model = Aircraft::class;

    public function definition(): array
    {
        return [
            'immatriculation' => strtoupper($this->faker->bothify('9S-###')),
            'pmad' => $this->faker->numberBetween(5000, 80000),
            'in_activity' => true,
            'aircraft_type_id' => AircraftType::factory(),
            'operator_id' => Operator::factory(),
        ];
    }
}
