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

class ListMaintenanceTasksTest extends TestCase
{
    use InteractsWithMaintenanceTasks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('listingRoleProvider')]
    public function test_allowed_roles_can_list_filtered_maintenance_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.index@example.com']);
        $vehicleSystem = VehicleSystem::factory()->create(['name' => 'Engine']);
        $matchingTask = $this->maintenanceTaskFor([
            'vehicle_system_id' => $vehicleSystem->id,
            'name' => 'Brake fluid inspection',
            'code' => 'BRAKE-FLUID-INSPECTION',
            'estimated_duration_minutes' => 60,
            'is_active' => true,
        ]);
        $this->maintenanceTaskFor([
            'name' => 'Inactive hidden task',
            'code' => 'INACTIVE-HIDDEN-TASK',
            'estimated_duration_minutes' => 240,
            'is_active' => false,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks?search=brake&vehicle_system_id='.$vehicleSystem->id.'&is_active=true&estimated_duration_from=30&estimated_duration_to=90')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonFragment(['code' => $matchingTask->code])
            ->assertJsonMissing(['code' => 'INACTIVE-HIDDEN-TASK']);
    }

    public function test_can_filter_reusable_and_vehicle_specific_tasks(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.task.vehicle.filter@example.com']);
        $vehicle = $this->vehicleFor(['license_plate' => 'TASK123']);
        $generalTask = $this->maintenanceTaskFor([
            'vehicle_id' => null,
            'name' => 'Reusable task',
            'code' => 'REUSABLE-TASK',
        ]);
        $vehicleTask = $this->maintenanceTaskFor([
            'vehicle_id' => $vehicle->id,
            'name' => 'Vehicle task',
            'code' => 'VEHICLE-TASK',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks?without_vehicle=true')
            ->assertOk()
            ->assertJsonFragment(['code' => $generalTask->code])
            ->assertJsonMissing(['code' => $vehicleTask->code]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks?vehicle_id='.$vehicle->id)
            ->assertOk()
            ->assertJsonFragment(['code' => $vehicleTask->code])
            ->assertJsonMissing(['code' => $generalTask->code]);
    }

    public function test_can_filter_by_status_and_created_dates(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.task.status.filter@example.com']);
        $matchingTask = $this->maintenanceTaskFor([
            'name' => 'Created status task',
            'code' => 'CREATED-STATUS-TASK',
            'status' => MaintenanceTaskStatus::Created->value,
            'created_at' => now()->subDay(),
        ]);
        $this->maintenanceTaskFor([
            'name' => 'Rejected status task',
            'code' => 'REJECTED-STATUS-TASK',
            'status' => MaintenanceTaskStatus::Rejected->value,
            'created_at' => now()->subMonth(),
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks?status=created&created_from='.now()->subDays(2)->toDateString().'&created_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonFragment(['code' => $matchingTask->code])
            ->assertJsonMissing(['code' => 'REJECTED-STATUS-TASK']);
    }

    #[DataProvider('nonListingRoleProvider')]
    public function test_non_catalog_roles_cannot_list_maintenance_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.index.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_maintenance_tasks(): void
    {
        $this->getJson('/api/v1/maintenance-tasks')->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function listingRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'advisor' => [SystemRole::Advisor];
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonListingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'technician' => [SystemRole::Technician];
    }
}
