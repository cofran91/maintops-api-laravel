<?php

namespace Tests\Feature\Api\MaintenanceTasks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\MaintenanceTasks\Concerns\InteractsWithMaintenanceTasks;
use Tests\TestCase;

class MaintenanceTaskRelationTest extends TestCase
{
    use InteractsWithMaintenanceTasks, RefreshDatabase;

    public function test_vehicle_has_many_maintenance_tasks(): void
    {
        $vehicle = $this->vehicleFor();
        $task = $this->maintenanceTaskFor(['vehicle_id' => $vehicle->id]);

        $this->assertTrue($vehicle->maintenanceTasks->contains($task));
    }

    public function test_vehicle_system_has_many_maintenance_tasks(): void
    {
        $task = $this->maintenanceTaskFor();

        $this->assertTrue($task->vehicleSystem->maintenanceTasks->contains($task));
    }
}
