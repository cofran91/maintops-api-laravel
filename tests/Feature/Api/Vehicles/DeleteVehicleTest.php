<?php

namespace Tests\Feature\Api\Vehicles;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Vehicles\Concerns\InteractsWithVehicles;
use Tests\TestCase;

class DeleteVehicleTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_delete_vehicle(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.delete@example.com']);
        $owner = $this->owner(['email' => $role->value.'.vehicle.delete.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => strtoupper(substr($role->value, 0, 3)).'987']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('message', 'Vehicle deleted successfully.');

        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);
    }

    #[DataProvider('nonDeletingRoleProvider')]
    public function test_non_admin_roles_cannot_delete_vehicle(SystemRole $role): void
    {
        $owner = $this->owner(['email' => $role->value.'.vehicle.delete.denied.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => strtoupper(substr($role->value, 0, 3)).'456']);
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.delete.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_delete_vehicle(): void
    {
        $owner = $this->owner(['email' => 'vehicle.delete.guest.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'GDE123']);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function systemAdminRoleProvider(): iterable
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
