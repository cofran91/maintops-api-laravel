<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class UpdateMaintenanceOrderTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.status.update@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.status.update@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.status.update.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::PendingOwnerApproval->value,
        ]);
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Approved->value,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance order updated successfully.')
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Approved->value);

        $this->assertDatabaseHas('maintenance_orders', [
            'id' => $order->id,
            'status' => MaintenanceOrderStatus::Approved->value,
        ]);
    }

    public function test_advisor_can_reject_any_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.reject@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.order.reject@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.reject.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $otherAdvisor);

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Rejected->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Rejected->value);
    }

    public function test_approval_with_rejected_items_marks_order_as_partially_approved(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.partial.approve@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.partial.approve@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.partial.approve.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::PendingOwnerApproval->value,
        ]);
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor());
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Rejected->value,
            'rejected_at' => now(),
        ]);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Approved->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrderStatus::PartiallyApproved->value);
    }

    public function test_assigned_workshop_manager_can_cancel_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.manager.cancel@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.order.cancel@example.com']);
        $workshop = $this->workshopFor($manager);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.manager.cancel.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now(),
        ]);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => now(),
        ]);

        $this->withToken($manager->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Cancelled->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Cancelled->value);

        $this->assertDatabaseHas('maintenance_order_items', [
            'id' => $item->id,
            'status' => MaintenanceOrderItemStatus::Cancelled->value,
        ]);
    }

    public function test_admin_can_deliver_completed_order(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.deliver@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.deliver@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.deliver.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Completed->value,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
        ]);
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Completed->value,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
        ]);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Delivered->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Delivered->value);

        $this->assertNotNull($order->refresh()->delivered_at);
    }

    public function test_order_status_update_rejects_invalid_state_transition(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.invalid.transition@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.invalid.transition@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.invalid.transition.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Approved->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    #[DataProvider('disallowedStatusProvider')]
    public function test_order_status_update_requires_role_specific_permission(
        SystemRole $role,
        MaintenanceOrderStatus $status
    ): void {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.advisor.order.status.denied@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.manager.order.status.denied@example.com']);
        $workshop = $this->workshopFor($manager);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => $role->value.'.order.status.denied.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, ['workshop_id' => $workshop->id]);

        $actor = match ($role) {
            SystemRole::Advisor => $advisor,
            SystemRole::WorkshopManager => $manager,
        };

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => $status->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_assigned_technician_cannot_update_order_status(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.tech.denied@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'manager.order.tech.denied@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'technician.order.tech.denied@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.tech.denied.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
        ]);

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-orders/'.$order->id, [
                'status' => MaintenanceOrderStatus::Cancelled->value,
            ])
            ->assertForbidden();
    }

    /**
     * @return iterable<string, array{SystemRole, MaintenanceOrderStatus}>
     */
    public static function disallowedStatusProvider(): iterable
    {
        yield 'advisor cannot deliver' => [SystemRole::Advisor, MaintenanceOrderStatus::Delivered];
        yield 'workshop manager cannot approve' => [SystemRole::WorkshopManager, MaintenanceOrderStatus::Approved];
    }
}
