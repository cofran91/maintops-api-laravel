<?php

namespace Tests\Feature\Console;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Enums\SystemRole;
use App\Enums\VehicleSystemCode;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenanceTask;
use App\Models\Owner;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSystem;
use App\Models\Workshop;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleApprovedMaintenanceOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_schedules_approved_order_in_one_workshop_with_one_technician(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));

        $engineId = $this->vehicleSystemId(VehicleSystemCode::Engine);
        $brakesId = $this->vehicleSystemId(VehicleSystemCode::Brakes);
        $decoyTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'schedule.decoy.tech@example.com']);
        $decoyWorkshop = $this->workshopWithTechnicians([$engineId], [$decoyTechnician], [
            'name' => 'Decoy Workshop',
            'code' => 'DECOY-WORKSHOP',
        ]);
        $technician = $this->userWithRole(SystemRole::Technician, ['email' => 'schedule.main.tech@example.com']);
        $workshop = $this->workshopWithTechnicians([$engineId, $brakesId], [$technician], [
            'name' => 'Main Workshop',
            'code' => 'MAIN-WORKSHOP',
        ]);
        [$order, $firstItem, $secondItem] = $this->approvedOrderWithItems([
            ['vehicle_system_id' => $engineId, 'estimated_duration_minutes' => 90, 'code' => $this->uniqueCode('SCHEDULE-ENGINE')],
            ['vehicle_system_id' => $brakesId, 'estimated_duration_minutes' => 60, 'code' => $this->uniqueCode('SCHEDULE-BRAKES')],
        ]);

        $this->artisan('maintenance-orders:schedule-approved')
            ->assertExitCode(0);

        $this->assertSame(MaintenanceOrderStatus::Scheduled->value, $order->refresh()->status->getValue());
        $this->assertSame($workshop->id, $order->workshop_id);
        $this->assertNotSame($decoyWorkshop->id, $order->workshop_id);
        $this->assertSame($technician->id, $order->technician_id);
        $this->assertSame('2026-06-15 08:00:00', $order->scheduled_at->toDateTimeString());
        $this->assertSame('2026-06-15 08:00:00', $firstItem->refresh()->scheduled_at->toDateTimeString());
        $this->assertSame('2026-06-15 09:30:00', $secondItem->refresh()->scheduled_at->toDateTimeString());
        $this->assertSame(90, $firstItem->planned_duration_minutes);
        $this->assertSame('2026-06-15 09:30:00', $firstItem->scheduled_ends_at->toDateTimeString());
        $this->assertSame(60, $secondItem->planned_duration_minutes);
        $this->assertSame('2026-06-15 10:30:00', $secondItem->scheduled_ends_at->toDateTimeString());
    }

    public function test_command_tries_other_workshops_today_before_moving_to_tomorrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 16:30:00'));

        $engineId = $this->vehicleSystemId(VehicleSystemCode::Engine);
        $busyTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'today.busy.tech@example.com']);
        $busyWorkshop = $this->workshopWithTechnicians([$engineId], [$busyTechnician], [
            'name' => 'Busy Today Workshop',
            'code' => 'BUSY-TODAY',
            'weekly_schedule' => [
                'monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'tuesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
            ],
        ]);
        $availableTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'today.available.tech@example.com']);
        $availableWorkshop = $this->workshopWithTechnicians([$engineId], [$availableTechnician], [
            'name' => 'Available Today Workshop',
            'code' => 'AVAILABLE-TODAY',
            'weekly_schedule' => [
                'monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'tuesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
            ],
        ]);
        $existingTask = $this->maintenanceTaskFor([
            'vehicle_system_id' => $engineId,
            'estimated_duration_minutes' => 60,
            'code' => $this->uniqueCode('BUSY-EXISTING'),
        ]);
        [$existingOrder] = $this->approvedOrderWithItems([]);
        $existingOrder->update([
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'workshop_id' => $busyWorkshop->id,
            'technician_id' => $busyTechnician->id,
            'scheduled_at' => Carbon::parse('2026-06-15 16:00:00'),
        ]);
        $this->maintenanceOrderItemFor($existingOrder, $existingTask, [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => Carbon::parse('2026-06-15 16:00:00'),
            'scheduled_ends_at' => Carbon::parse('2026-06-15 17:00:00'),
            'planned_duration_minutes' => 60,
        ]);

        [$order, $item] = $this->approvedOrderWithItems([
            ['vehicle_system_id' => $engineId, 'estimated_duration_minutes' => 30, 'code' => $this->uniqueCode('TODAY-ITEM')],
        ]);

        $this->artisan('maintenance-orders:schedule-approved')
            ->assertExitCode(0);

        $this->assertSame(MaintenanceOrderStatus::Scheduled->value, $order->refresh()->status->getValue());
        $this->assertSame($availableWorkshop->id, $order->workshop_id);
        $this->assertSame($availableTechnician->id, $order->technician_id);
        $this->assertSame('2026-06-15 16:30:00', $item->refresh()->scheduled_at->toDateTimeString());
    }

    public function test_command_uses_another_technician_when_first_has_no_time_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 16:30:00'));

        $engineId = $this->vehicleSystemId(VehicleSystemCode::Engine);
        $busyTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'same.workshop.busy.tech@example.com']);
        $availableTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'same.workshop.available.tech@example.com']);
        $workshop = $this->workshopWithTechnicians([$engineId], [$busyTechnician, $availableTechnician], [
            'name' => 'Same Day Technician Switch',
            'code' => 'SAME-DAY-TECH-SWITCH',
            'weekly_schedule' => [
                'monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
            ],
        ]);
        $existingTask = $this->maintenanceTaskFor([
            'vehicle_system_id' => $engineId,
            'estimated_duration_minutes' => 60,
            'code' => $this->uniqueCode('TECH-BUSY-EXISTING'),
        ]);
        [$existingOrder] = $this->approvedOrderWithItems([]);
        $existingOrder->update([
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'workshop_id' => $workshop->id,
            'technician_id' => $busyTechnician->id,
            'scheduled_at' => Carbon::parse('2026-06-15 16:00:00'),
        ]);
        $this->maintenanceOrderItemFor($existingOrder, $existingTask, [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => Carbon::parse('2026-06-15 16:00:00'),
            'scheduled_ends_at' => Carbon::parse('2026-06-15 17:00:00'),
            'planned_duration_minutes' => 60,
        ]);

        [$order, $item] = $this->approvedOrderWithItems([
            ['vehicle_system_id' => $engineId, 'estimated_duration_minutes' => 30, 'code' => $this->uniqueCode('TECH-SWITCH-ITEM')],
        ]);

        $this->artisan('maintenance-orders:schedule-approved')
            ->assertExitCode(0);

        $this->assertSame(MaintenanceOrderStatus::Scheduled->value, $order->refresh()->status->getValue());
        $this->assertSame($workshop->id, $order->workshop_id);
        $this->assertSame($availableTechnician->id, $order->technician_id);
        $this->assertSame('2026-06-15 16:30:00', $item->refresh()->scheduled_at->toDateTimeString());
    }

    public function test_command_splits_items_to_next_workday_when_first_day_has_some_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 15:30:00'));

        $engineId = $this->vehicleSystemId(VehicleSystemCode::Engine);
        $technician = $this->userWithRole(SystemRole::Technician, ['email' => 'split.tech@example.com']);
        $this->workshopWithTechnicians([$engineId], [$technician], [
            'name' => 'Split Workshop',
            'code' => 'SPLIT-WORKSHOP',
            'weekly_schedule' => [
                'monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'tuesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
            ],
        ]);
        [$order, $firstItem, $secondItem] = $this->approvedOrderWithItems([
            ['vehicle_system_id' => $engineId, 'estimated_duration_minutes' => 60, 'code' => $this->uniqueCode('SPLIT-FIRST')],
            ['vehicle_system_id' => $engineId, 'estimated_duration_minutes' => 60, 'code' => $this->uniqueCode('SPLIT-SECOND')],
        ]);

        $this->artisan('maintenance-orders:schedule-approved')
            ->assertExitCode(0);

        $this->assertSame(MaintenanceOrderStatus::Scheduled->value, $order->refresh()->status->getValue());
        $this->assertSame('2026-06-15 15:30:00', $firstItem->refresh()->scheduled_at->toDateTimeString());
        $this->assertSame('2026-06-16 08:00:00', $secondItem->refresh()->scheduled_at->toDateTimeString());
    }

    /**
     * @param  array<int, int>  $vehicleSystemIds
     * @param  array<int, User>  $technicians
     * @param  array<string, mixed>  $attributes
     */
    private function workshopWithTechnicians(array $vehicleSystemIds, array $technicians, array $attributes = []): Workshop
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, [
            'email' => $this->uniqueCode('WORKSHOP-MANAGER').'@example.com',
        ]);
        $workshop = Workshop::factory()->create(array_merge([
            'manager_user_id' => $manager->id,
        ], $attributes));
        $workshop->vehicleSystems()->sync($vehicleSystemIds);

        foreach ($technicians as $technician) {
            $technician->update(['workshop_id' => $workshop->id]);
        }

        return $workshop->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $taskAttributes
     * @return array<int, MaintenanceOrder|MaintenanceOrderItem>
     */
    private function approvedOrderWithItems(
        array $taskAttributes,
        MaintenanceOrderStatus $status = MaintenanceOrderStatus::Approved,
    ): array {
        $owner = Owner::factory()->create([
            'email' => $this->uniqueCode('OWNER').'@example.com',
        ]);
        $advisor = $this->userWithRole(SystemRole::Advisor, [
            'email' => $this->uniqueCode('ADVISOR').'@example.com',
        ]);
        $vehicle = Vehicle::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $order = MaintenanceOrder::factory()->create([
            'vehicle_id' => $vehicle->id,
            'advisor_id' => $advisor->id,
            'workshop_id' => null,
            'technician_id' => null,
            'status' => $status->value,
        ]);
        $result = [$order];

        foreach ($taskAttributes as $attributes) {
            $task = $this->maintenanceTaskFor(array_merge([
                'vehicle_id' => null,
                'vehicle_system_id' => $this->vehicleSystemId(VehicleSystemCode::Engine),
                'code' => $this->uniqueCode('SCHEDULE-TASK'),
            ], $attributes));

            $result[] = $this->maintenanceOrderItemFor($order, $task, [
                'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                'pending_owner_approval_at' => null,
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(SystemRole $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => $role->value.'.'.uniqid('', true).'@example.com',
        ], $attributes));

        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceTaskFor(array $attributes = []): MaintenanceTask
    {
        return MaintenanceTask::factory()->create(array_merge([
            'vehicle_id' => null,
            'vehicle_system_id' => $this->vehicleSystemId(),
            'status' => MaintenanceTaskStatus::Created->value,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceOrderItemFor(
        MaintenanceOrder $order,
        MaintenanceTask $task,
        array $attributes = [],
    ): MaintenanceOrderItem {
        return MaintenanceOrderItem::factory()->create(array_merge([
            'maintenance_order_id' => $order->id,
            'maintenance_task_id' => $task->id,
            'maintenance_plan_id' => null,
            'planned_duration_minutes' => $task->estimated_duration_minutes,
            'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            'pending_owner_approval_at' => now(),
        ], $attributes));
    }

    private function vehicleSystemId(VehicleSystemCode $code = VehicleSystemCode::Engine): int
    {
        return (int) VehicleSystem::query()
            ->where('code', $code->value)
            ->value('id');
    }

    private function uniqueCode(string $prefix): string
    {
        return strtoupper(str_replace('.', '-', uniqid($prefix.'-', true)));
    }
}
