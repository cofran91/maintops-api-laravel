<?php

namespace Database\Factories;

use App\Enums\VehicleSystemCode;
use App\Models\VehicleSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleSystem>
 */
class VehicleSystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $system = fake()->unique()->randomElement(VehicleSystemCode::cases());

        return [
            'code' => $system->value,
            'name' => $system->label(),
        ];
    }
}
