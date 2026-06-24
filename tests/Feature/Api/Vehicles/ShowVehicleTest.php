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

class ShowVehicleTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('vehicleReaderRoleProvider')]
    public function test_allowed_roles_can_show_vehicle(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.show@example.com']);
        $owner = $this->owner(['email' => $role->value.'.vehicle.show.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'SHW123']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('message', 'Vehicle retrieved.')
            ->assertJsonPath('data.id', $vehicle->id)
            ->assertJsonPath('data.owner.id', $owner->id);
    }

    public function test_technician_cannot_show_vehicle(): void
    {
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.vehicle.show@example.com']);
        $owner = $this->owner(['email' => 'vehicle.show.denied.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'DEN456']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_show_vehicle(): void
    {
        $owner = $this->owner(['email' => 'vehicle.show.guest.owner@example.com']);
        $vehicle = $this->vehicleFor($owner, ['license_plate' => 'GST456']);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id)->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function vehicleReaderRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
    }
}
