<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Basic preventive maintenance',
            'Complete engine inspection',
            'Brake and suspension plan',
            'Mileage maintenance plan',
            'Pre-operation inspection',
        ]);

        return [
            'code' => Str::upper(Str::slug($name, '-')).'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'description' => fake()->sentence(),
            'recommended_interval_days' => fake()->numberBetween(30, 365),
            'recommended_interval_km' => fake()->numberBetween(5000, 50000),
            'is_active' => true,
        ];
    }
}
