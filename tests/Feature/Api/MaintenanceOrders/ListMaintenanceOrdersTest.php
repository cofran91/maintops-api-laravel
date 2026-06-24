<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class ListMaintenanceOrdersTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_system_admin_can_list_all_orders(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.list@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.list@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.order.list@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.order.list@example.com']);
        $otherManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'other.manager.order.list@example.com']);
        $workshop = $this->workshopFor($manager);
        $otherWorkshop = $this->workshopFor($otherManager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.order.list@example.com']);
        $otherTechnician = $this->technicianFor($otherWorkshop, ['email' => 'other.technician.order.list@example.com']);
        $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'first.order.list.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'second.order.list.owner@example.com'])),
            $otherAdvisor,
            ['workshop_id' => $otherWorkshop->id, 'technician_id' => $otherTechnician->id],
        );

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_advisor_can_list_all_orders(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.list.all@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.order.list.all@example.com']);
        $firstOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'first.advisor.order.list.owner@example.com'])),
            $advisor,
        );
        $secondOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'second.advisor.order.list.owner@example.com'])),
            $otherAdvisor,
        );

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $secondOrder->id)
            ->assertJsonPath('data.items.1.id', $firstOrder->id);
    }

    #[DataProvider('scopedRoleProvider')]
    public function test_list_orders_is_scoped_by_role_assignment(SystemRole $role): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.advisor.order.list@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.other.advisor.order.list@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.manager.order.list@example.com']);
        $otherManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.other.manager.order.list@example.com']);
        $workshop = $this->workshopFor($manager);
        $otherWorkshop = $this->workshopFor($otherManager);
        $technician = $this->technicianFor($workshop, ['email' => $role->value.'.technician.order.list@example.com']);
        $otherTechnician = $this->technicianFor($otherWorkshop, ['email' => $role->value.'.other.technician.order.list@example.com']);
        $firstOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => $role->value.'.first.order.list.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $secondOrder = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => $role->value.'.second.order.list.owner@example.com'])),
            $otherAdvisor,
            ['workshop_id' => $otherWorkshop->id, 'technician_id' => $otherTechnician->id],
        );

        $actor = match ($role) {
            SystemRole::WorkshopManager => $manager,
            SystemRole::Technician => $technician,
        };

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $firstOrder->id);

        $this->assertNotSame($firstOrder->id, $secondOrder->id);
    }

    public function test_orders_can_be_filtered_by_status_and_assignment(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.filter@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.filter@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.order.filter@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.order.filter@example.com']);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'order.filter.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'other.order.filter.owner@example.com'])),
            $advisor,
        );

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders?workshop_id='.$workshop->id.'&technician_id='.$technician->id.'&status=created')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $order->id);
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
