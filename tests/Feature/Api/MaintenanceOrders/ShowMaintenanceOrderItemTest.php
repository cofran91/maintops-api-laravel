<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class ShowMaintenanceOrderItemTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_assigned_technician_can_show_item(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.show@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.item.show@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.item.show@example.com']);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'item.show.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items/'.$item->id)
            ->assertOk()
            ->assertJsonPath('data.id', $item->id);
    }

    public function test_advisor_can_show_any_item(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.show.any@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.item.show.any@example.com']);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'item.show.any.owner@example.com'])),
            $otherAdvisor,
        );
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items/'.$item->id)
            ->assertOk()
            ->assertJsonPath('data.id', $item->id);
    }

    public function test_unassigned_technician_cannot_show_item(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.show.denied@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.item.show.denied@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.item.show.denied@example.com']);
        $otherTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'other.technician.item.show.denied@example.com']);
        $order = $this->maintenanceOrderFor(
            $this->vehicleFor($this->ownerFor(['email' => 'item.show.denied.owner@example.com'])),
            $advisor,
            ['workshop_id' => $workshop->id, 'technician_id' => $technician->id],
        );
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($otherTechnician->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-order-items/'.$item->id)
            ->assertForbidden();
    }
}
