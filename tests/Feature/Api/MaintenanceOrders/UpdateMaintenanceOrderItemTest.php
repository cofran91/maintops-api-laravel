<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class UpdateMaintenanceOrderItemTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_admin_can_update_item_status(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.item.status.update@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.status.update@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'item.status.update.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-order-items/'.$item->id, [
                'status' => MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance order item updated successfully.')
            ->assertJsonPath('data.status', MaintenanceOrderItemStatus::InProgress->value);

        $this->assertDatabaseHas('maintenance_order_items', [
            'id' => $item->id,
            'status' => MaintenanceOrderItemStatus::InProgress->value,
        ]);
    }

    public function test_advisor_can_reject_item_from_any_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.reject@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.item.reject@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'item.reject.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $otherAdvisor);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-order-items/'.$item->id, [
                'status' => MaintenanceOrderItemStatus::Rejected->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrderItemStatus::Rejected->value);
    }

    public function test_assigned_workshop_manager_can_cancel_item(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.item.manager.cancel@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.item.cancel@example.com']);
        $workshop = $this->workshopFor($manager);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'item.manager.cancel.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, ['workshop_id' => $workshop->id]);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($manager->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-order-items/'.$item->id, [
                'status' => MaintenanceOrderItemStatus::Cancelled->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrderItemStatus::Cancelled->value);
    }

    #[DataProvider('technicianStatusProvider')]
    public function test_assigned_technician_can_update_operational_item_status(
        MaintenanceOrderItemStatus $status
    ): void {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $status->value.'.advisor.item.tech.update@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $status->value.'.manager.item.tech.update@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => $status->value.'.technician.item.update@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => $status->value.'.item.tech.update.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
        ]);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-order-items/'.$item->id, [
                'status' => $status->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', $status->value);
    }

    #[DataProvider('disallowedStatusProvider')]
    public function test_item_status_update_requires_role_specific_permission(
        SystemRole $role,
        MaintenanceOrderItemStatus $status
    ): void {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.advisor.item.status.denied@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.manager.item.status.denied@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => $role->value.'.technician.item.status.denied@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => $role->value.'.item.status.denied.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
        ]);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $actor = match ($role) {
            SystemRole::Advisor => $advisor,
            SystemRole::WorkshopManager => $manager,
            SystemRole::Technician => $technician,
        };

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-order-items/'.$item->id, [
                'status' => $status->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * @return iterable<string, array{MaintenanceOrderItemStatus}>
     */
    public static function technicianStatusProvider(): iterable
    {
        yield 'in progress' => [MaintenanceOrderItemStatus::InProgress];
        yield 'completed' => [MaintenanceOrderItemStatus::Completed];
    }

    /**
     * @return iterable<string, array{SystemRole, MaintenanceOrderItemStatus}>
     */
    public static function disallowedStatusProvider(): iterable
    {
        yield 'advisor cannot complete' => [SystemRole::Advisor, MaintenanceOrderItemStatus::Completed];
        yield 'workshop manager cannot complete' => [SystemRole::WorkshopManager, MaintenanceOrderItemStatus::Completed];
        yield 'technician cannot reject' => [SystemRole::Technician, MaintenanceOrderItemStatus::Rejected];
    }
}
