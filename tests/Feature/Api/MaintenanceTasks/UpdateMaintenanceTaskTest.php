<?php

namespace Tests\Feature\Api\MaintenanceTasks;

use App\Enums\MaintenanceTaskStatus;
use App\Enums\SystemRole;
use App\Models\VehicleSystem;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceTasks\Concerns\InteractsWithMaintenanceTasks;
use Tests\TestCase;

class UpdateMaintenanceTaskTest extends TestCase
{
    use InteractsWithMaintenanceTasks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_update_maintenance_task(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.update@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'UPDATE-TASK']);
        $vehicle = $this->vehicleFor();
        $vehicleSystem = VehicleSystem::factory()->create();

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-tasks/'.$task->id, $this->maintenanceTaskUpdatePayload($task, [
                'vehicle_id' => $vehicle->id,
                'vehicle_system_id' => $vehicleSystem->id,
                'name' => 'Updated oil service',
                'code' => 'updated oil service',
                'description' => 'Updated task description.',
                'estimated_duration_minutes' => 75,
                'is_active' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.vehicle_system_id', $vehicleSystem->id)
            ->assertJsonPath('data.code', 'UPDATED-OIL-SERVICE')
            ->assertJsonPath('data.status', MaintenanceTaskStatus::Created->value)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('maintenance_tasks', [
            'id' => $task->id,
            'vehicle_id' => $vehicle->id,
            'vehicle_system_id' => $vehicleSystem->id,
            'code' => 'UPDATED-OIL-SERVICE',
            'status' => MaintenanceTaskStatus::Created->value,
            'is_active' => false,
        ]);
    }

    public function test_update_requires_full_payload(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.task.full.update@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'FULL-UPDATE-TASK']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-tasks/'.$task->id, [
                'name' => 'Partial update',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'vehicle_system_id',
                'code',
                'estimated_duration_minutes',
                'is_active',
            ]);
    }

    public function test_task_status_cannot_be_updated_from_task_endpoint(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.task.status.update@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'STATUS-UPDATE-TASK']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-tasks/'.$task->id, $this->maintenanceTaskUpdatePayload($task, [
                'status' => MaintenanceTaskStatus::Scheduled->value,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('maintenance_tasks', [
            'id' => $task->id,
            'status' => MaintenanceTaskStatus::Created->value,
        ]);
    }

    #[DataProvider('nonUpdatingRoleProvider')]
    public function test_non_admin_roles_cannot_update_maintenance_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.update.denied@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-UPDATE-DENIED']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-tasks/'.$task->id, $this->maintenanceTaskUpdatePayload($task))
            ->assertForbidden();
    }

    public function test_guest_cannot_update_maintenance_task(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'GUEST-UPDATE-DENIED']);

        $this->patchJson('/api/v1/maintenance-tasks/'.$task->id, $this->maintenanceTaskUpdatePayload($task))
            ->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function systemAdminProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonUpdatingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
