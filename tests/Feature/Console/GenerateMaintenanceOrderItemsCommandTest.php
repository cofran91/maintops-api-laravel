<?php

namespace Tests\Feature\Console;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Enums\SystemRole;
use App\Enums\VehicleSystemCode;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Owner;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSystem;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMaintenanceOrderItemsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_command_creates_items_for_due_plans_and_direct_vehicle_tasks(): void
    {
        [$vehicle, $order] = $this->createdOrderWithVehicle([
            'odometer_km' => 12500,
            'created_at' => now()->subDays(10),
        ]);

        $planTask = $this->maintenanceTaskFor([
            'vehicle_id' => null,
            'vehicle_system_id' => $this->vehicleSystemId(),
            'code' => $this->uniqueCode('PLAN-TASK'),
        ]);
        $plan = $this->maintenancePlanFor([$planTask->id], [
            'code' => $this->uniqueCode('PLAN-DUE'),
            'recommended_interval_days' => null,
            'recommended_interval_km' => 10000,
            'is_active' => true,
        ]);
        $directTask = $this->maintenanceTaskFor([
            'vehicle_id' => $vehicle->id,
            'code' => $this->uniqueCode('DIRECT-TASK'),
        ]);

        $this->artisan('maintenance-orders:generate-items')
            ->assertExitCode(0);

        $this->assertSame(MaintenanceOrderStatus::PendingOwnerApproval->value, $order->refresh()->status->getValue());
        $this->assertDatabaseHas('maintenance_order_items', [
            'maintenance_order_id' => $order->id,
            'maintenance_task_id' => $planTask->id,
            'maintenance_plan_id' => $plan->id,
            'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            'odometer_km' => 12500,
        ]);
        $this->assertDatabaseHas('maintenance_order_items', [
            'maintenance_order_id' => $order->id,
            'maintenance_task_id' => $directTask->id,
            'maintenance_plan_id' => null,
            'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            'odometer_km' => 12500,
        ]);
        $this->assertSame(2, MaintenanceOrderItem::query()->where('maintenance_order_id', $order->id)->count());

        $this->artisan('maintenance-orders:generate-items')
            ->assertExitCode(0);

        $this->assertSame(2, MaintenanceOrderItem::query()->where('maintenance_order_id', $order->id)->count());
    }

    public function test_command_uses_last_plan_execution_date_and_odometer(): void
    {
        [$vehicle, $order, $advisor] = $this->createdOrderWithVehicle([
            'odometer_km' => 14000,
            'created_at' => now()->subDays(200),
        ]);

        $planTask = $this->maintenanceTaskFor([
            'vehicle_system_id' => $this->vehicleSystemId(),
            'code' => $this->uniqueCode('PLAN-NOT-DUE-TASK'),
        ]);
        $plan = $this->maintenancePlanFor([$planTask->id], [
            'code' => $this->uniqueCode('PLAN-NOT-DUE'),
            'recommended_interval_days' => 90,
            'recommended_interval_km' => 10000,
        ]);
        $completedOrder = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Completed->value,
            'started_at' => now()->subDays(30),
            'finished_at' => now()->subDays(29),
        ]);
        $this->maintenanceOrderItemFor($completedOrder, $planTask, [
            'maintenance_plan_id' => $plan->id,
            'status' => MaintenanceOrderItemStatus::Completed->value,
            'started_at' => now()->subDays(30),
            'finished_at' => now()->subDays(29),
            'odometer_km' => 9000,
        ]);

        $this->artisan('maintenance-orders:generate-items')
            ->assertExitCode(0);

        $this->assertSame(0, MaintenanceOrderItem::query()->where('maintenance_order_id', $order->id)->count());
        $this->assertSame(MaintenanceOrderStatus::Created->value, $order->refresh()->status->getValue());

        $vehicle->update(['odometer_km' => 19000]);

        $this->artisan('maintenance-orders:generate-items')
            ->assertExitCode(0);

        $this->assertDatabaseHas('maintenance_order_items', [
            'maintenance_order_id' => $order->id,
            'maintenance_task_id' => $planTask->id,
            'maintenance_plan_id' => $plan->id,
            'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            'odometer_km' => 19000,
        ]);
        $this->assertSame(MaintenanceOrderStatus::PendingOwnerApproval->value, $order->refresh()->status->getValue());
    }

    /**
     * @param  array<string, mixed>  $vehicleAttributes
     * @return array{0: Vehicle, 1: MaintenanceOrder, 2: User}
     */
    private function createdOrderWithVehicle(array $vehicleAttributes = []): array
    {
        $owner = Owner::factory()->create([
            'email' => $this->uniqueCode('OWNER').'@example.com',
        ]);
        $advisor = $this->userWithRole(SystemRole::Advisor, [
            'email' => $this->uniqueCode('ADVISOR').'@example.com',
        ]);
        $vehicle = Vehicle::factory()->create(array_merge([
            'owner_id' => $owner->id,
        ], $vehicleAttributes));
        $order = $this->maintenanceOrderFor($vehicle, $advisor);

        return [$vehicle, $order, $advisor];
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
     * @param  array<int, int>  $taskIds
     * @param  array<string, mixed>  $attributes
     */
    private function maintenancePlanFor(array $taskIds, array $attributes = []): MaintenancePlan
    {
        $plan = MaintenancePlan::factory()->create($attributes);
        $plan->tasks()->sync($taskIds);

        return $plan->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenanceOrderFor(Vehicle $vehicle, User $advisor, array $attributes = []): MaintenanceOrder
    {
        return MaintenanceOrder::factory()->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'advisor_id' => $advisor->id,
            'workshop_id' => null,
            'technician_id' => null,
            'status' => MaintenanceOrderStatus::Created->value,
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
