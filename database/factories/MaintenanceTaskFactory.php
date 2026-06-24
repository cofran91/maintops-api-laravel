<?php

namespace Database\Factories;

use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceTask;
use App\Models\VehicleSystem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaintenanceTask>
 */
class MaintenanceTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Oil change',
            'Brake inspection',
            'Wheel alignment',
            'Electrical diagnosis',
            'Filter replacement',
        ]);

        return [
            'vehicle_id' => null,
            'vehicle_system_id' => VehicleSystem::query()->inRandomOrder()->value('id') ?? VehicleSystem::factory(),
            'name' => $name.' '.fake()->unique()->numberBetween(1000, 9999),
            'code' => Str::upper(Str::slug($name, '-')).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(),
            'estimated_duration_minutes' => fake()->numberBetween(30, 240),
            'status' => MaintenanceTaskStatus::Created->value,
            'is_active' => true,
        ];
    }
}
