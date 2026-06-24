<?php

namespace Tests\Feature\Api\MaintenancePlans;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenancePlans\Concerns\InteractsWithMaintenancePlans;
use Tests\TestCase;

class ListMaintenancePlansTest extends TestCase
{
    use InteractsWithMaintenancePlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_list_filtered_maintenance_plans(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.index@example.com']);
        $targetTask = $this->maintenanceTaskFor(['code' => 'FILTER-TASK', 'name' => 'Filter replacement']);
        $otherTask = $this->maintenanceTaskFor(['code' => 'OTHER-FILTER-TASK']);
        $plan = $this->maintenancePlanFor([$targetTask->id], [
            'code' => 'FILTER-PLAN',
            'name' => 'Filter maintenance plan',
            'recommended_interval_days' => 120,
            'recommended_interval_km' => 8000,
            'is_active' => true,
        ]);
        $this->maintenancePlanFor([$otherTask->id], [
            'code' => 'BRAKE-PLAN',
            'name' => 'Brake maintenance plan',
            'recommended_interval_days' => 30,
            'recommended_interval_km' => 2000,
            'is_active' => true,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-plans?search=filter&task_id='.$targetTask->id.'&is_active=true&recommended_interval_days_from=90&recommended_interval_km_to=10000')
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance plans retrieved.')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $plan->id)
            ->assertJsonPath('data.items.0.task_ids.0', $targetTask->id);
    }

    public function test_can_filter_by_created_dates_and_interval_ranges(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.plan.date.filter@example.com']);
        $task = $this->maintenanceTaskFor(['code' => 'DATE-FILTER-TASK']);
        $matchingPlan = $this->maintenancePlanFor([$task->id], [
            'code' => 'DATE-FILTER-PLAN',
            'recommended_interval_days' => 180,
            'recommended_interval_km' => 10000,
            'created_at' => now()->subDay(),
        ]);
        $this->maintenancePlanFor([$task->id], [
            'code' => 'OLD-FILTER-PLAN',
            'recommended_interval_days' => 30,
            'recommended_interval_km' => 1000,
            'created_at' => now()->subMonth(),
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-plans?recommended_interval_days_to=200&recommended_interval_km_from=5000&created_from='.now()->subDays(2)->toDateString().'&created_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonFragment(['code' => $matchingPlan->code])
            ->assertJsonMissing(['code' => 'OLD-FILTER-PLAN']);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_list_maintenance_plans(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.plan.index.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/maintenance-plans')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_maintenance_plans(): void
    {
        $this->getJson('/api/v1/maintenance-plans')->assertUnauthorized();
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
