<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class ListMaintenanceOrderItemsTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_system_admin_can_list_all_items(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.item.list@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.list@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.item.list@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.item.list@example.com']);
        $otherManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'other.manager.item.list@example.com']);
        $workshop = $this->workshopFor($manager);
        $otherWorkshop = $this->workshopFor($otherManager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.item.list@example.com']);
        $otherTechnician = $this->technicianFor($otherWorkshop, ['email' => 'other.technician.item.list@example.com']);
        $firstOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'first.item.list.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $secondOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'second.item.list.owner@example.com'])),
            $otherAdvisor,
            ['workshop_id' => $otherWorkshop->id, 'technician_id' => $otherTechnician->id],
        );
        $this->maintenanceOrderItemFor($firstOrder, $this->maintenanceTaskFor());
        $this->maintenanceOrderItemFor($secondOrder, $this->maintenanceTaskFor());

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_advisor_can_list_all_items(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.list.all@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.item.list.all@example.com']);
        $firstOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'first.advisor.item.list.owner@example.com'])),
            $advisor,
        );
        $secondOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'second.advisor.item.list.owner@example.com'])),
            $otherAdvisor,
        );
        $firstItem = $this->maintenanceOrderItemFor($firstOrder, $this->maintenanceTaskFor());
        $secondItem = $this->maintenanceOrderItemFor($secondOrder, $this->maintenanceTaskFor());

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $secondItem->id)
            ->assertJsonPath('data.items.1.id', $firstItem->id);
    }

    #[DataProvider('scopedRoleProvider')]
    public function test_list_items_is_scoped_by_parent_order_assignment(SystemRole $role): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.advisor.item.list@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.other.advisor.item.list@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.manager.item.list@example.com']);
        $otherManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.other.manager.item.list@example.com']);
        $workshop = $this->workshopFor($manager);
        $otherWorkshop = $this->workshopFor($otherManager);
        $technician = $this->technicianFor($workshop, ['email' => $role->value.'.technician.item.list@example.com']);
        $otherTechnician = $this->technicianFor($otherWorkshop, ['email' => $role->value.'.other.technician.item.list@example.com']);
        $firstOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => $role->value.'.first.item.list.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $secondOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => $role->value.'.second.item.list.owner@example.com'])),
            $otherAdvisor,
            ['workshop_id' => $otherWorkshop->id, 'technician_id' => $otherTechnician->id],
        );
        $firstItem = $this->maintenanceOrderItemFor($firstOrder, $this->maintenanceTaskFor());
        $this->maintenanceOrderItemFor($secondOrder, $this->maintenanceTaskFor());

        $actor = match ($role) {
            SystemRole::WorkshopManager => $manager,
            SystemRole::Technician => $technician,
        };

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $firstItem->id);
    }

    public function test_items_can_be_filtered_by_order_task_and_plan(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.item.filter@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.filter@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'item.filter.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor);
        $task = $this->maintenanceTaskFor();
        $plan = $this->maintenancePlanFor([$task->id]);
        $item = $this->maintenanceOrderItemFor($order, $task, ['maintenance_plan_id' => $plan->id]);
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items?maintenance_order_id='.$order->id.'&maintenance_task_id='.$task->id.'&maintenance_plan_id='.$plan->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $item->id);
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function scopedRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'technician' => [SystemRole::Technician];
    }
}
