<?php

namespace Database\Factories;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceOrderItem>
 */
class MaintenanceOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_order_id' => MaintenanceOrder::factory(),
            'maintenance_task_id' => MaintenanceTask::factory(),
            'maintenance_plan_id' => fake()->optional()->randomElement([
                null,
                MaintenancePlan::factory(),
            ]),
            'odometer_km' => null,
            'planned_duration_minutes' => null,
            'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            'pending_owner_approval_at' => now(),
            'scheduled_at' => null,
            'scheduled_ends_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
        ];
    }
}
