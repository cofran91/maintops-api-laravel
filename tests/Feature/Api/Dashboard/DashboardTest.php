<?php

namespace Tests\Feature\Api\Dashboard;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admin_can_read_global_dashboard(SystemRole $role): void
    {
        Date::setTestNow('2026-06-24 10:00:00');

        $admin = $this->userWithRole($role, ['email' => 'dashboard.'.$role->value.'@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'dashboard.'.$role->value.'.advisor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'dashboard.'.$role->value.'.manager@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'dashboard.'.$role->value.'.technician@example.com']);
        $owner = $this->ownerFor(['email' => 'dashboard.'.$role->value.'.owner@example.com']);
        $vehicle = $this->vehicleFor($owner);
        $scheduledOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
        $awaitingSchedulingOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Approved->value,
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
        ]);
        $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Created->value,
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
        ]);
        $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Completed->value,
            'finished_at' => now()->subMinutes(30),
        ]);
        $this->maintenanceOrderItemFor($scheduledOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => now()->subHour(),
        ]);
        $this->maintenanceOrderItemFor($scheduledOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::InProgress->value,
        ]);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dashboard retrieved.')
            ->assertJsonPath('data.orders_by_status.created', 1)
            ->assertJsonPath('data.orders_by_status.approved', 1)
            ->assertJsonPath('data.orders_by_status.scheduled', 1)
            ->assertJsonPath('data.orders_by_status.completed', 1)
            ->assertJsonPath('data.metrics.total_orders', 4)
            ->assertJsonPath('data.metrics.awaiting_scheduling', 1)
            ->assertJsonPath('data.metrics.completed_today', 1)
            ->assertJsonPath('data.metrics.overdue_activities', 1)
            ->assertJsonPath('data.activities.pending', 1)
            ->assertJsonPath('data.activities.active', 1)
            ->assertJsonPath('data.today_schedules.0.maintenance_order_id', $scheduledOrder->id)
            ->assertJsonMissingPath('data.alerts')
            ->assertJsonPath('data.role_context.role', $role->value)
            ->assertJsonPath('data.role_context.type', 'system_admin')
            ->assertJsonPath('data.role_context.orders_by_workshop.0.workshop_id', $workshop->id)
            ->assertJsonPath('data.role_context.technician_workload_today.0.technician_id', $technician->id)
            ->assertJsonPath('data.role_context.awaiting_scheduling_orders.0.maintenance_order_id', $awaitingSchedulingOrder->id);
    }

    public function test_advisor_dashboard_focuses_on_owner_approval_and_delivery_follow_up(): void
    {
        Date::setTestNow('2026-06-24 10:00:00');

        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'dashboard.advisor.context@example.com']);
        $owner = $this->ownerFor(['email' => 'dashboard.advisor.owner@example.com']);
        $vehicle = $this->vehicleFor($owner);
        $pendingOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::PendingOwnerApproval->value,
        ]);
        $partiallyApprovedOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::PartiallyApproved->value,
        ]);
        $rejectedOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Rejected->value,
        ]);
        $completedOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Completed->value,
            'finished_at' => now()->subHour(),
        ]);

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role_context.role', SystemRole::Advisor->value)
            ->assertJsonPath('data.role_context.type', 'advisor')
            ->assertJsonPath('data.role_context.awaiting_owner_approval_orders.0.maintenance_order_id', $pendingOrder->id)
            ->assertJsonPath('data.role_context.partially_approved_orders.0.maintenance_order_id', $partiallyApprovedOrder->id)
            ->assertJsonPath('data.role_context.rejected_orders.0.maintenance_order_id', $rejectedOrder->id)
            ->assertJsonPath('data.role_context.upcoming_deliveries.0.maintenance_order_id', $completedOrder->id);
    }

    public function test_workshop_manager_sees_only_own_dashboard(): void
    {
        Date::setTestNow('2026-06-24 10:00:00');

        $ownManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'dashboard.own.manager@example.com']);
        $otherManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'dashboard.other.manager@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'dashboard.manager.advisor@example.com']);
        $ownWorkshop = $this->workshopFor($ownManager);
        $otherWorkshop = $this->workshopFor($otherManager);
        $technician = $this->technicianFor($ownWorkshop, ['email' => 'dashboard.manager.technician@example.com']);
        $idleTechnician = $this->technicianFor($ownWorkshop, ['email' => 'dashboard.manager.idle.technician@example.com']);
        $owner = $this->ownerFor(['email' => 'dashboard.manager.owner@example.com']);
        $vehicle = $this->vehicleFor($owner);
        $todayOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $ownWorkshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
        $upcomingOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $ownWorkshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ]);
        $otherOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $otherWorkshop->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
        $this->maintenanceOrderItemFor($todayOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
            'planned_duration_minutes' => 90,
        ]);
        $activeItem = $this->maintenanceOrderItemFor($todayOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::InProgress->value,
            'scheduled_at' => now()->addMinutes(30),
            'planned_duration_minutes' => 45,
        ]);
        $this->maintenanceOrderItemFor($otherOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);

        $this->withToken($ownManager->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.orders_by_status.scheduled', 2)
            ->assertJsonPath('data.metrics.total_orders', 2)
            ->assertJsonPath('data.activities.pending', 1)
            ->assertJsonPath('data.today_schedules.0.maintenance_order_id', $todayOrder->id)
            ->assertJsonPath('data.upcoming_schedules.0.maintenance_order_id', $upcomingOrder->id)
            ->assertJsonPath('data.role_context.role', SystemRole::WorkshopManager->value)
            ->assertJsonPath('data.role_context.type', 'workshop_manager')
            ->assertJsonPath('data.role_context.technician_workload_today.0.technician_id', $technician->id)
            ->assertJsonPath('data.role_context.technician_workload_today.0.planned_minutes', 135)
            ->assertJsonPath('data.role_context.technicians_without_assignments_today.0.id', $idleTechnician->id)
            ->assertJsonPath('data.role_context.active_items.0.maintenance_order_item_id', $activeItem->id);
    }

    public function test_technician_sees_only_assigned_dashboard(): void
    {
        Date::setTestNow('2026-06-24 10:00:00');

        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'dashboard.tech.manager@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'dashboard.tech.advisor@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'dashboard.tech@example.com']);
        $otherTechnician = $this->technicianFor($workshop, ['email' => 'dashboard.other.tech@example.com']);
        $owner = $this->ownerFor(['email' => 'dashboard.tech.owner@example.com']);
        $vehicle = $this->vehicleFor($owner);
        $assignedOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::InProgress->value,
            'scheduled_at' => now()->subHour(),
        ]);
        $nextOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
        $otherOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $otherTechnician->id,
            'status' => MaintenanceOrderStatus::InProgress->value,
            'scheduled_at' => now()->subHour(),
        ]);
        $currentItem = $this->maintenanceOrderItemFor($assignedOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::InProgress->value,
            'scheduled_at' => now()->subHour(),
        ]);
        $nextItem = $this->maintenanceOrderItemFor($nextOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
        $this->maintenanceOrderItemFor($assignedOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Completed->value,
            'finished_at' => now()->subMinutes(10),
        ]);
        $this->maintenanceOrderItemFor($otherOrder, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::InProgress->value,
        ]);

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.orders_by_status.in_progress', 1)
            ->assertJsonPath('data.orders_by_status.scheduled', 1)
            ->assertJsonPath('data.metrics.total_orders', 2)
            ->assertJsonPath('data.activities.active', 1)
            ->assertJsonPath('data.today_schedules.0.maintenance_order_id', $assignedOrder->id)
            ->assertJsonPath('data.role_context.role', SystemRole::Technician->value)
            ->assertJsonPath('data.role_context.type', 'technician')
            ->assertJsonPath('data.role_context.current_item.maintenance_order_item_id', $currentItem->id)
            ->assertJsonPath('data.role_context.next_item.maintenance_order_item_id', $nextItem->id)
            ->assertJsonPath('data.role_context.today_queue.0.maintenance_order_item_id', $currentItem->id)
            ->assertJsonPath('data.role_context.completed_today_count', 1);
    }

    public function test_guest_cannot_read_dashboard(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function systemAdminRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
    }
}
