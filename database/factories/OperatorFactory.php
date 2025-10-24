<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Operator;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $regimes = ['domestic', 'international'];
        $types = ['regular', 'non_regular'];
        $natures = ['commercial', 'non_commercial'];

        return [
            'name' => $this->faker->company(),
            'iata_code' => strtoupper($this->faker->unique()->lexify('??')),
            'icao_code' => strtoupper($this->faker->unique()->lexify('???')),
            'flight_regime' => $this->faker->randomElement($regimes),
            'flight_type' => $this->faker->randomElement($types),
            'flight_nature' => $this->faker->randomElement($natures),
            'country' => $this->faker->country(),
        ];
    }
}
