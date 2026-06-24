<?php

namespace Tests\Feature\Api\MaintenanceTasks\Concerns;

use App\Enums\MaintenanceTaskStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceTask;
use App\Models\Owner;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSystem;

trait InteractsWithMaintenanceTasks
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
    private function vehicleFor(array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'owner_id' => Owner::factory(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function maintenanceTaskPayload(array $attributes = []): array
    {
        return array_merge([
            'vehicle_id' => null,
            'vehicle_system_id' => VehicleSystem::factory()->create()->id,
            'name' => 'Oil change service',
            'code' => 'oil change service',
            'description' => 'Drain oil, replace filter, and register odometer.',
            'estimated_duration_minutes' => 90,
            'is_active' => true,
        ], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceTaskFor(array $attributes = []): MaintenanceTask
    {
        return MaintenanceTask::factory()->create(array_merge([
            'status' => MaintenanceTaskStatus::Created->value,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function maintenanceTaskUpdatePayload(MaintenanceTask $maintenanceTask, array $attributes = []): array
    {
        return array_merge([
            'vehicle_id' => $maintenanceTask->vehicle_id,
            'vehicle_system_id' => $maintenanceTask->vehicle_system_id,
            'name' => $maintenanceTask->name,
            'code' => $maintenanceTask->code,
            'description' => $maintenanceTask->description,
            'estimated_duration_minutes' => $maintenanceTask->estimated_duration_minutes,
            'is_active' => (bool) $maintenanceTask->is_active,
        ], $attributes);
    }
}
