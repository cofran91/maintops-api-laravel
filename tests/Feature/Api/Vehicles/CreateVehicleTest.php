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

class CreateVehicleTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('vehicleCreatorRoleProvider')]
    public function test_allowed_roles_can_create_vehicle_for_owner(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.create@example.com']);
        $owner = $this->owner(['email' => $role->value.'.vehicle.owner@example.com']);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/vehicles', $this->vehiclePayloadFor($owner))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle created successfully.')
            ->assertJsonPath('data.owner_id', $owner->id)
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.license_plate', 'ABC123')
            ->assertJsonPath('data.brand', 'Toyota')
            ->assertJsonPath('data.model', 'Hilux');

        $this->assertDatabaseHas('vehicles', [
            'id' => $response->json('data.id'),
            'owner_id' => $owner->id,
            'license_plate' => 'ABC123',
            'year' => 2024,
            'odometer_km' => 15200,
        ]);
    }

    public function test_vehicle_requires_active_owner_and_valid_payload(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'vehicle.validation.actor@example.com']);
        $inactiveOwner = $this->owner([
            'email' => 'vehicle.invalid.owner@example.com',
            'is_active' => false,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/vehicles', array_merge($this->vehiclePayloadFor($inactiveOwner), [
                'license_plate' => '',
                'year' => 1899,
                'odometer_km' => -1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'owner_id',
                'license_plate',
                'year',
                'odometer_km',
            ]);
    }

    public function test_vehicle_requires_unique_license_plate(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'vehicle.duplicate.actor@example.com']);
        $owner = $this->owner(['email' => 'vehicle.duplicate.owner@example.com']);
        $this->vehicleFor($owner, ['license_plate' => 'DUP123']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/vehicles', $this->vehiclePayloadFor($owner, [
                'license_plate' => 'dup123',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_plate']);
    }

    public function test_technician_cannot_create_vehicle(): void
    {
        $owner = $this->owner(['email' => 'vehicle.create.denied.owner@example.com']);
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.vehicle.create@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/vehicles', $this->vehiclePayloadFor($owner))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_create_vehicle(): void
    {
        $owner = $this->owner(['email' => 'vehicle.create.guest.owner@example.com']);

        $this->postJson('/api/v1/vehicles', $this->vehiclePayloadFor($owner))->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function vehicleCreatorRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
    }
}
