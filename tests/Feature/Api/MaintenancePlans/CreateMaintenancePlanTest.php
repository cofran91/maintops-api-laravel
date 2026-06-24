<?php

namespace Tests\Feature\Api\MaintenancePlans;

use App\Enums\SystemRole;
use App\Models\Audit;
use App\Models\MaintenancePlan;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenancePlans\Concerns\InteractsWithMaintenancePlans;
use Tests\TestCase;

class CreateMaintenancePlanTest extends TestCase
{
    use InteractsWithMaintenancePlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_create_maintenance_plan_with_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.create@example.com']);
        $firstTask = $this->maintenanceTaskFor(['code' => 'CREATE-PLAN-TASK-1']);
        $secondTask = $this->maintenanceTaskFor(['code' => 'CREATE-PLAN-TASK-2']);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-plans', $this->maintenancePlanPayload([
                'task_ids' => [$firstTask->id, $secondTask->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('message', 'Maintenance plan created successfully.')
            ->assertJsonPath('data.code', 'PREVENTIVE-10K')
            ->assertJsonPath('data.name', 'Preventive 10k')
            ->assertJsonPath('data.recommended_interval_days', 180)
            ->assertJsonPath('data.recommended_interval_km', 10000)
            ->assertJsonPath('data.is_active', true);

        $createdId = $response->json('data.id');

        $this->assertEqualsCanonicalizing(
            [$firstTask->id, $secondTask->id],
            $response->json('data.task_ids'),
        );
        $this->assertDatabaseHas('maintenance_plans', [
            'id' => $createdId,
            'code' => 'PREVENTIVE-10K',
            'name' => 'Preventive 10k',
        ]);
        $this->assertDatabaseHas('maintenance_plan_maintenance_task', [
            'maintenance_plan_id' => $createdId,
            'maintenance_task_id' => $firstTask->id,
        ]);
        $this->assertDatabaseHas('maintenance_plan_maintenance_task', [
            'maintenance_plan_id' => $createdId,
            'maintenance_task_id' => $secondTask->id,
        ]);
    }

    public function test_plan_creation_with_tasks_is_recorded_as_one_business_audit(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.plan.audit.create@example.com']);
        $firstTask = $this->maintenanceTaskFor(['code' => 'AUDIT-PLAN-TASK-1']);
        $secondTask = $this->maintenanceTaskFor(['code' => 'AUDIT-PLAN-TASK-2']);

        $createdId = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-plans', $this->maintenancePlanPayload([
                'code' => 'audit plan',
                'task_ids' => [$firstTask->id, $secondTask->id],
            ]))
            ->assertCreated()
            ->json('data.id');

        $audit = Audit::query()
            ->where('event', 'maintenance plan created')
            ->where('auditable_type', (new MaintenancePlan)->getMorphClass())
            ->where('auditable_id', $createdId)
            ->firstOrFail();

        $this->assertSame($actor->id, $audit->user_id);
        $this->assertEqualsCanonicalizing(
            [$firstTask->id, $secondTask->id],
            $audit->new_values['maintenance_task_ids'],
        );
        $this->assertDatabaseMissing('audits', [
            'event' => 'created',
            'auditable_type' => (new MaintenancePlan)->getMorphClass(),
            'auditable_id' => $createdId,
        ]);
    }

    public function test_maintenance_plan_requires_active_reusable_tasks_and_valid_intervals(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.plan.validation@example.com']);
        $inactiveTask = $this->maintenanceTaskFor(['is_active' => false]);
        $vehicleSpecificTask = $this->maintenanceTaskFor(['vehicle_id' => $this->vehicleFor()->id]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-plans', [
                'code' => '',
                'name' => '',
                'recommended_interval_days' => 0,
                'recommended_interval_km' => 0,
                'is_active' => true,
                'task_ids' => [$inactiveTask->id, $vehicleSpecificTask->id, 999999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'recommended_interval_days',
                'recommended_interval_km',
                'task_ids.0',
                'task_ids.1',
                'task_ids.2',
            ]);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_create_maintenance_plans(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.create.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-plans', $this->maintenancePlanPayload([
                'code' => $role->value.' denied plan',
            ]))
            ->assertForbidden();
    }

    public function test_guest_cannot_create_maintenance_plan(): void
    {
        $this->postJson('/api/v1/maintenance-plans', $this->maintenancePlanPayload())
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
    public static function nonManagingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
