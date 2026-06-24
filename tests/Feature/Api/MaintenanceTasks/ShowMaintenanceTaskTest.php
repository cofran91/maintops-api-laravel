<?php

namespace Tests\Feature\Api\MaintenanceTasks;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceTasks\Concerns\InteractsWithMaintenanceTasks;
use Tests\TestCase;

class ShowMaintenanceTaskTest extends TestCase
{
    use InteractsWithMaintenanceTasks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('viewingRoleProvider')]
    public function test_allowed_roles_can_show_maintenance_task(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.show@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'SHOW-TASK']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks/'.$task->id)
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.code', 'SHOW-TASK');
    }

    #[DataProvider('nonViewingRoleProvider')]
    public function test_non_catalog_roles_cannot_show_maintenance_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.show.denied@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'SHOW-DENIED']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-tasks/'.$task->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_show_maintenance_task(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'GUEST-SHOW-DENIED']);

        $this->getJson('/api/v1/maintenance-tasks/'.$task->id)->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function viewingRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'advisor' => [SystemRole::Advisor];
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonViewingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'technician' => [SystemRole::Technician];
    }
}
