<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class ShowMaintenanceOrderTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_assigned_workshop_manager_and_technician_can_show_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.show@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.order.show@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.order.show@example.com']);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'order.show.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );

        $this->withToken($manager->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_advisor_can_show_any_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.show.any@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.order.show.any@example.com']);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'order.show.any.owner@example.com'])),
            $otherAdvisor,
        );

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_unassigned_workshop_manager_cannot_show_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.show.denied@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.order.show.denied@example.com']);
        $otherManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'other.manager.order.show.denied@example.com']);
        $workshop = $this->workshopFor($manager);
        $this->workshopFor($otherManager);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'order.show.denied.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id],
        );

        $this->withToken($otherManager->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-orders/'.$order->id)
            ->assertForbidden();
    }
}
