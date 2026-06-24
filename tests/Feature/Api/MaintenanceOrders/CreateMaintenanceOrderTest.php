<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class CreateMaintenanceOrderTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_create_order_for_advisor(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.order.create@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.order.advisor@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => $role->value.'.order.owner@example.com']));

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $advisor))
            ->assertCreated()
            ->assertJsonPath('message', 'Maintenance order created successfully.')
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.advisor_id', $advisor->id)
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Created->value);

        $this->assertDatabaseHas('maintenance_orders', [
            'id' => $response->json('data.id'),
            'vehicle_id' => $vehicle->id,
            'advisor_id' => $advisor->id,
            'status' => MaintenanceOrderStatus::Created->value,
        ]);
    }

    public function test_advisor_creates_order_assigned_to_self_when_advisor_is_omitted(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.create.omitted@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'advisor.order.omitted.owner@example.com']));

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', [
                'vehicle_id' => $vehicle->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.advisor_id', $advisor->id)
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Created->value);
    }

    public function test_advisor_creates_order_assigned_to_self_when_another_advisor_is_sent(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.create@example.com']);
        $otherAdvisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'other.advisor.order.create@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'advisor.order.owner@example.com']));

        $this->withToken($advisor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $otherAdvisor))
            ->assertCreated()
            ->assertJsonPath('data.advisor_id', $advisor->id)
            ->assertJsonPath('data.status', MaintenanceOrderStatus::Created->value);
    }

    public function test_order_requires_active_advisor_when_advisor_is_sent(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.advisor.validation@example.com']);
        $notAdvisor = $this->userWithRole(SystemRole::Technician, ['email' => 'not.advisor.order.assignment@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.advisor.validation.owner@example.com']));

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $notAdvisor))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['advisor_id']);
    }

    public function test_vehicle_cannot_have_two_open_orders(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.order.duplicate@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.order.duplicate@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'order.duplicate.owner@example.com']));
        $this->maintenanceOrderFor($vehicle, $advisor);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $advisor))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    #[DataProvider('nonCreatingRoleProvider')]
    public function test_workshop_roles_cannot_create_orders(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.order.create.denied@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => $role->value.'.order.denied.advisor@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => $role->value.'.order.denied.owner@example.com']));

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $advisor))
            ->assertForbidden();
    }

    public function test_guest_cannot_create_maintenance_order(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'guest.order.advisor@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'guest.order.owner@example.com']));

        $this->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $advisor))
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
