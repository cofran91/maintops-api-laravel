<?php

namespace Tests\Feature\Api\MaintenanceTasks;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceTasks\Concerns\InteractsWithMaintenanceTasks;
use Tests\TestCase;

class DeleteMaintenanceTaskTest extends TestCase
{
    use InteractsWithMaintenanceTasks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_delete_maintenance_task(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.delete@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-DELETE-TASK']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/maintenance-tasks/'.$task->id)
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance task deleted successfully.');

        $this->assertSoftDeleted('maintenance_tasks', ['id' => $task->id]);
    }

    #[DataProvider('nonDeletingRoleProvider')]
    public function test_non_admin_roles_cannot_delete_maintenance_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.task.delete.denied@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-DELETE-DENIED']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/maintenance-tasks/'.$task->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_delete_maintenance_task(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'GUEST-DELETE-DENIED']);

        $this->deleteJson('/api/v1/maintenance-tasks/'.$task->id)->assertUnauthorized();
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
    public static function nonDeletingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
