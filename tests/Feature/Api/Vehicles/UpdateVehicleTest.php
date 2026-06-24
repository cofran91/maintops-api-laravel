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

class UpdateVehicleTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('vehicleManagerRoleProvider')]
    public function test_allowed_roles_can_update_vehicle(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.update@example.com']);
        $owner = $this->owner(['email' => $role->value.'.vehicle.update.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'UPD123']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/vehicles/'.$vehicle->id, $this->vehicleUpdatePayloadFor($vehicle, [
                'license_plate' => 'upd999',
                'color' => 'Black',
                'odometer_km' => 32100,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Vehicle updated successfully.')
            ->assertJsonPath('data.license_plate', 'UPD999')
            ->assertJsonPath('data.color', 'Black')
            ->assertJsonPath('data.odometer_km', 32100);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => 'UPD999',
            'color' => 'Black',
            'odometer_km' => 32100,
        ]);
    }

    public function test_update_requires_full_payload(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'vehicle.update.validation@example.com']);
        $owner = $this->owner(['email' => 'vehicle.update.validation.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'REQ123']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/vehicles/'.$vehicle->id, ['color' => 'Blue'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id', 'license_plate', 'odometer_km']);
    }

    public function test_vehicle_cannot_be_reassigned_to_inactive_owner(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'vehicle.reassign.actor@example.com']);
        $owner = $this->owner(['email' => 'vehicle.reassign.owner@example.com']);
        $inactiveOwner = $this->owner([
            'email' => 'vehicle.reassign.inactive@example.com',
            'is_active' => false,
        ]);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'OWN789']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/vehicles/'.$vehicle->id, $this->vehicleUpdatePayloadFor($vehicle, [
                'owner_id' => $inactiveOwner->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id']);
    }

    public function test_vehicle_update_requires_unique_license_plate(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'vehicle.update.duplicate.actor@example.com']);
        $owner = $this->owner(['email' => 'vehicle.update.duplicate.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'SRC123']);
        $existing = $this->vehicleFor($owner, ['license_plate' => 'DUP321']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/vehicles/'.$vehicle->id, $this->vehicleUpdatePayloadFor($vehicle, [
                'license_plate' => strtolower($existing->license_plate),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_plate']);
    }

    #[DataProvider('nonUpdatingRoleProvider')]
    public function test_advisor_and_technician_cannot_update_vehicle(SystemRole $role): void
    {
        $owner = $this->owner(['email' => $role->value.'.vehicle.update.denied.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => strtoupper(substr($role->value, 0, 3)).'123']);
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.update.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/vehicles/'.$vehicle->id, $this->vehicleUpdatePayloadFor($vehicle, ['color' => 'Red']))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_update_vehicle(): void
    {
        $owner = $this->owner(['email' => 'vehicle.update.guest.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'GUP123']);

        $this->patchJson('/api/v1/vehicles/'.$vehicle->id, $this->vehicleUpdatePayloadFor($vehicle))->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function vehicleManagerRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'workshop manager' => [SystemRole::WorkshopManager];
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonUpdatingRoleProvider(): iterable
    {
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
