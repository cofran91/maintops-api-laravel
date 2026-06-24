<?php

namespace Tests\Feature\Api\MaintenancePlans;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenancePlans\Concerns\InteractsWithMaintenancePlans;
use Tests\TestCase;

class ShowMaintenancePlanTest extends TestCase
{
    use InteractsWithMaintenancePlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_show_maintenance_plan(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.show@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'SHOW-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => 'SHOW-PLAN']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-plans/'.$plan->id)
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.code', 'SHOW-PLAN')
            ->assertJsonPath('data.task_ids.0', $task->id);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_show_maintenance_plan(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.show.denied@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-SHOW-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => $role->value.'-SHOW-DENIED']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-plans/'.$plan->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_show_maintenance_plan(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'GUEST-SHOW-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => 'GUEST-SHOW-DENIED']);

        $this->getJson('/api/v1/maintenance-plans/'.$plan->id)->assertUnauthorized();
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
