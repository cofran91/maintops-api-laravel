<?php

namespace Tests\Feature\Api\MaintenancePlans;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenancePlans\Concerns\InteractsWithMaintenancePlans;
use Tests\TestCase;

class DeleteMaintenancePlanTest extends TestCase
{
    use InteractsWithMaintenancePlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_delete_maintenance_plan(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.delete@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-DELETE-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => $role->value.'-DELETE-PLAN']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/maintenance-plans/'.$plan->id)
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance plan deleted successfully.');

        $this->assertSoftDeleted('maintenance_plans', ['id' => $plan->id]);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_delete_maintenance_plan(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.delete.denied@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-DELETE-DENIED-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => $role->value.'-DELETE-DENIED']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/maintenance-plans/'.$plan->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_delete_maintenance_plan(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'GUEST-DELETE-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => 'GUEST-DELETE-DENIED']);

        $this->deleteJson('/api/v1/maintenance-plans/'.$plan->id)->assertUnauthorized();
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
    public static function nonManagingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
