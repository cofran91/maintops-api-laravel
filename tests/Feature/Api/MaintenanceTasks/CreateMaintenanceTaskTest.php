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

class CreateMaintenanceTaskTest extends TestCase
{
    use InteractsWithMaintenanceTasks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_create_reusable_maintenance_task(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.create@example.com']);
        $vehicleSystem = VehicleSystem::factory()->create();

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-tasks', $this->maintenanceTaskPayload([
                'vehicle_system_id' => $vehicleSystem->id,
                'code' => 'oil change service',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.vehicle_id', null)
            ->assertJsonPath('data.vehicle_system_id', $vehicleSystem->id)
            ->assertJsonPath('data.code', 'OIL-CHANGE-SERVICE')
            ->assertJsonPath('data.status', MaintenanceTaskStatus::Created->value)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('maintenance_tasks', [
            'vehicle_id' => null,
            'vehicle_system_id' => $vehicleSystem->id,
            'code' => 'OIL-CHANGE-SERVICE',
            'status' => MaintenanceTaskStatus::Created->value,
            'is_active' => true,
        ]);
    }

    public function test_advisor_can_create_vehicle_specific_maintenance_task_only(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.task.create@example.com']);
        $vehicle = $this->vehicleFor();

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-tasks', $this->maintenanceTaskPayload([
                'vehicle_id' => $vehicle->id,
                'name' => 'Advisor vehicle diagnosis',
                'code' => 'advisor vehicle diagnosis',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.vehicle_id', $vehicle->id);

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-tasks', $this->maintenanceTaskPayload([
                'vehicle_id' => null,
                'name' => 'Advisor reusable diagnosis',
                'code' => 'advisor reusable diagnosis',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_maintenance_task_rejects_invalid_relations_and_status_input(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.task.validation@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-tasks', [
                'vehicle_id' => 999999,
                'vehicle_system_id' => 999999,
                'name' => 'Invalid task',
                'code' => 'invalid task',
                'description' => 'Invalid relation payload.',
                'estimated_duration_minutes' => 0,
                'is_active' => true,
                'status' => MaintenanceTaskStatus::Scheduled->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'vehicle_id',
                'vehicle_system_id',
                'estimated_duration_minutes',
                'status',
            ]);
    }

    #[DataProvider('nonCreatingRoleProvider')]
    public function test_non_catalog_roles_cannot_create_maintenance_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.create.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-tasks', $this->maintenanceTaskPayload())
            ->assertForbidden();
    }

    public function test_guest_cannot_create_maintenance_task(): void
    {
        $this->postJson('/api/v1/maintenance-tasks', $this->maintenanceTaskPayload())
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
    public static function nonCreatingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'technician' => [SystemRole::Technician];
    }
}
