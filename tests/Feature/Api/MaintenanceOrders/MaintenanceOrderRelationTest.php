<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class MaintenanceOrderRelationTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_order_and_item_relationships_are_available(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.relations@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.relations.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor);
        $task = $this->maintenanceTaskFor();
        $plan = $this->maintenancePlanFor([$task->id]);
        $item = $this->maintenanceOrderItemFor($order, $task, ['maintenance_plan_id' => $plan->id]);

        $this->assertTrue($vehicle->maintenanceOrders->contains(fn (MaintenanceOrder $related): bool => $related->is($order)));
        $this->assertTrue($advisor->advisedMaintenanceOrders->contains(fn (MaintenanceOrder $related): bool => $related->is($order)));
        $this->assertTrue($order->items->contains(fn (MaintenanceOrderItem $related): bool => $related->is($item)));
        $this->assertTrue($task->maintenanceOrderItems->contains(fn (MaintenanceOrderItem $related): bool => $related->is($item)));
        $this->assertTrue($plan->maintenanceOrderItems->contains(fn (MaintenanceOrderItem $related): bool => $related->is($item)));
        $this->assertTrue($item->maintenanceOrder->is($order));
        $this->assertTrue($item->maintenanceTask->is($task));
        $this->assertTrue($item->maintenancePlan->is($plan));
    }
}
