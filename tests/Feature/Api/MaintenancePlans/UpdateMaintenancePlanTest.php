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

class UpdateMaintenancePlanTest extends TestCase
{
    use InteractsWithMaintenancePlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_update_maintenance_plan_and_replace_tasks(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.update@example.com']);
        $oldTask = $this->maintenanceTaskFor(['code' => $role->value.'-OLD-PLAN-TASK']);
        $newTask = $this->maintenanceTaskFor(['code' => $role->value.'-NEW-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$oldTask->id], ['code' => $role->value.'-UPDATE-PLAN']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-plans/'.$plan->id, $this->maintenancePlanPayload([
                'code' => 'updated preventive plan',
                'name' => 'Updated preventive plan',
                'recommended_interval_days' => 365,
                'recommended_interval_km' => 20000,
                'task_ids' => [$newTask->id],
                'is_active' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance plan updated successfully.')
            ->assertJsonPath('data.code', 'UPDATED-PREVENTIVE-PLAN')
            ->assertJsonPath('data.name', 'Updated preventive plan')
            ->assertJsonPath('data.task_ids.0', $newTask->id)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('maintenance_plans', [
            'id' => $plan->id,
            'code' => 'UPDATED-PREVENTIVE-PLAN',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('maintenance_plan_maintenance_task', [
            'maintenance_plan_id' => $plan->id,
            'maintenance_task_id' => $newTask->id,
        ]);
        $this->assertDatabaseMissing('maintenance_plan_maintenance_task', [
            'maintenance_plan_id' => $plan->id,
            'maintenance_task_id' => $oldTask->id,
        ]);
    }

    public function test_plan_update_with_tasks_is_recorded_as_one_business_audit(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.plan.audit.update@example.com']);
        $oldTask = $this->maintenanceTaskFor(['code' => 'AUDIT-OLD-PLAN-TASK']);
        $newTask = $this->maintenanceTaskFor(['code' => 'AUDIT-NEW-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$oldTask->id], ['code' => 'AUDIT-UPDATE-PLAN']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-plans/'.$plan->id, $this->maintenancePlanPayload([
                'code' => 'audit updated plan',
                'task_ids' => [$newTask->id],
            ]))
            ->assertOk();

        $audit = Audit::query()
            ->where('event', 'maintenance plan updated')
            ->where('auditable_type', (new MaintenancePlan)->getMorphClass())
            ->where('auditable_id', $plan->id)
            ->firstOrFail();

        $this->assertSame($actor->id, $audit->user_id);
        $this->assertEqualsCanonicalizing([$oldTask->id], $audit->old_values['maintenance_task_ids']);
        $this->assertEqualsCanonicalizing([$newTask->id], $audit->new_values['maintenance_task_ids']);
        $this->assertDatabaseMissing('audits', [
            'event' => 'updated',
            'auditable_type' => (new MaintenancePlan)->getMorphClass(),
            'auditable_id' => $plan->id,
        ]);
    }

    public function test_update_requires_full_payload_and_valid_task_ids(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.plan.full.update@example.com']);
        $vehicleSpecificTask = $this->maintenanceTaskFor(['vehicle_id' => $this->vehicleFor()->id]);
        $plan = $this->maintenancePlanFor([
            $this->maintenanceTaskFor(['code' => 'FULL-UPDATE-PLAN-TASK'])->id,
        ], ['code' => 'FULL-UPDATE-PLAN']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-plans/'.$plan->id, [
                'name' => 'Partial update',
                'task_ids' => [$vehicleSpecificTask->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'is_active',
                'task_ids.0',
            ]);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_update_maintenance_plan(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.update.denied@example.com']);
        $task = $this->maintenanceTaskFor(['code' => $role->value.'-UPDATE-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => $role->value.'-UPDATE-DENIED']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/maintenance-plans/'.$plan->id, $this->maintenancePlanPayload([
                'code' => $role->value.' updated denied plan',
                'task_ids' => [$task->id],
            ]))
            ->assertForbidden();
    }

    public function test_guest_cannot_update_maintenance_plan(): void
    {
        $task = $this->maintenanceTaskFor(['code' => 'GUEST-UPDATE-PLAN-TASK']);
        $plan = $this->maintenancePlanFor([$task->id], ['code' => 'GUEST-UPDATE-DENIED']);

        $this->patchJson('/api/v1/maintenance-plans/'.$plan->id, $this->maintenancePlanPayload([
            'task_ids' => [$task->id],
        ]))->assertUnauthorized();
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
