<?php

namespace Tests\Feature\Api\MaintenancePlans;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\MaintenancePlans\Concerns\InteractsWithMaintenancePlans;
use Tests\TestCase;

class MaintenancePlanRelationTest extends TestCase
{
    use InteractsWithMaintenancePlans, RefreshDatabase;

    public function test_maintenance_plan_belongs_to_many_tasks(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'PLAN-RELATION-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => 'PLAN-RELATION']);

        $this->assertTrue($plan->tasks->contains($task));
    }

    public function test_maintenance_task_belongs_to_many_plans(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'TASK-PLAN-RELATION']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => 'TASK-PLAN-RELATION']);

        $this->assertTrue($task->maintenancePlans->contains($plan));
    }
}
