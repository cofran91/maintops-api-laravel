<?php

namespace Database\Factories;

use App\Enums\MaintenanceOrderStatus;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceOrder>
 */
class MaintenanceOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'advisor_id' => User::factory(),
            'workshop_id' => fake()->optional()->randomElement([
                null,
                Workshop::factory(),
            ]),
            'technician_id' => null,
            'status' => MaintenanceOrderStatus::Created->value,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'delivered_at' => null,
            'cancelled_at' => null,
        ];
    }
}
