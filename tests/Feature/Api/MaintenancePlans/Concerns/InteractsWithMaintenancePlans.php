<?php

namespace Tests\Feature\Api\MaintenancePlans\Concerns;

use App\Enums\MaintenanceTaskStatus;
use App\Enums\SystemRole;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Owner;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSystem;

trait InteractsWithMaintenancePlans
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
     */
    private function maintenanceTaskFor(array $attributes = []): MaintenanceTask
    {
        return MaintenanceTask::factory()->create(array_merge([
            'vehicle_id' => null,
            'vehicle_system_id' => VehicleSystem::factory(),
            'status' => MaintenanceTaskStatus::Created->value,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function maintenancePlanPayload(array $attributes = []): array
    {
        $payload = [
            'code' => 'preventive 10k',
            'name' => 'Preventive 10k',
            'description' => 'Recommended activities every 10000 km.',
            'recommended_interval_days' => 180,
            'recommended_interval_km' => 10000,
            'is_active' => true,
        ];

        if (! array_key_exists('task_ids', $attributes)) {
            $payload['task_ids'] = [
                $this->maintenanceTaskFor(['code' => $this->uniqueCode('PLAN-TASK')])->id,
                $this->maintenanceTaskFor(['code' => $this->uniqueCode('PLAN-TASK')])->id,
            ];
        }

        return array_merge($payload, $attributes);
    }

    /**
     * @param  array<int, int>  $taskIds
     * @param  array<string, mixed>  $attributes
     */
    private function maintenancePlanFor(array $taskIds, array $attributes = []): MaintenancePlan
    {
        $maintenancePlan = MaintenancePlan::factory()->create($attributes);
        $maintenancePlan->tasks()->sync($taskIds);

        return $maintenancePlan->refresh();
    }

    private function uniqueCode(string $prefix): string
    {
        return strtoupper(str_replace('.', '-', uniqid($prefix.'-', true)));
    }
}
