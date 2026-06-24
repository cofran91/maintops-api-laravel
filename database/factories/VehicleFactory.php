<?php

namespace Database\Factories;

use App\Models\Owner;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => Owner::factory(),
            'license_plate' => strtoupper(fake()->unique()->bothify('???###')),
            'brand' => fake()->randomElement(['Toyota', 'Chevrolet', 'Mazda', 'Renault', 'Nissan']),
            'model' => fake()->randomElement(['Hilux', 'Spark', 'CX-5', 'Logan', 'Frontier']),
            'year' => fake()->numberBetween(2010, now()->year),
            'color' => fake()->safeColorName(),
            'odometer_km' => fake()->numberBetween(0, 250000),
        ];
    }
}
