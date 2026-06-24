<?php

namespace Tests\Feature\Api\MaintenanceOrders\Concerns;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Owner;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSystem;
use App\Models\Workshop;

trait InteractsWithMaintenanceOrders
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(SystemRole $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => $role->value.'.'.uniqid('', true).'@example.com',
        ], $attributes));

        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownerFor(array $attributes = []): Owner
    {
        return Owner::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicleFor(Owner $owner, array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'owner_id' => $owner->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function workshopFor(User $manager, array $attributes = []): Workshop
    {
        return Workshop::factory()->create(array_merge([
            'manager_user_id' => $manager->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function technicianFor(Workshop $workshop, array $attributes = []): User
    {
        return $this->userWithRole(SystemRole::Technician, array_merge([
            'workshop_id' => $workshop->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceTaskFor(array $attributes = []): MaintenanceTask
    {
        return MaintenanceTask::factory()->create(array_merge([
            'vehicle_id' => null,
            'vehicle_system_id' => $this->vehicleSystem()->id,
            'status' => MaintenanceTaskStatus::Created->value,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<int, int>  $taskIds
     * @param  array<string, mixed>  $attributes
     */
    private function maintenancePlanFor(array $taskIds, array $attributes = []): MaintenancePlan
    {
        $plan = MaintenancePlan::factory()->create($attributes);
        $plan->tasks()->sync($taskIds);

        return $plan->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceOrderFor(Vehicle $vehicle, User $advisor, array $attributes = []): MaintenanceOrder
    {
        return MaintenanceOrder::factory()->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'advisor_id' => $advisor->id,
            'workshop_id' => null,
            'technician_id' => null,
            'status' => MaintenanceOrderStatus::Created->value,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceOrderItemFor(
        MaintenanceOrder $order,
        MaintenanceTask $task,
        array $attributes = []
    ): MaintenanceOrderItem {
        return MaintenanceOrderItem::factory()->create(array_merge([
            'maintenance_order_id' => $order->id,
            'maintenance_task_id' => $task->id,
            'maintenance_plan_id' => null,
            'planned_duration_minutes' => $task->estimated_duration_minutes,
            'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            'pending_owner_approval_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function maintenanceOrderPayload(
        Vehicle $vehicle,
        User $advisor,
        array $attributes = []
    ): array {
        return array_merge([
            'vehicle_id' => $vehicle->id,
            'advisor_id' => $advisor->id,
        ], $attributes);
    }

    private function vehicleSystem(): VehicleSystem
    {
        return VehicleSystem::query()->first()
            ?? VehicleSystem::factory()->create();
    }
}
