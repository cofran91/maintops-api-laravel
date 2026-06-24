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

class ListVehiclesTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('vehicleReaderRoleProvider')]
    public function test_allowed_roles_can_list_filtered_vehicles(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.vehicle.index@example.com']);
        $owner = $this->owner([
            'name' => 'Vehicle Owner',
            'email' => 'vehicle.index.owner@example.com',
            'document_number' => 'VEHICLE-OWNER',
        ]);
        $vehicle = $this->vehicleFor($owner, [
            'license_plate' => 'ABC987',
            'brand' => 'Toyota',
            'model' => 'Hilux',
        ]);
        $this->vehicleFor($owner, ['license_plate' => 'ZZZ111']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicles?search=ABC')
            ->assertOk()
            ->assertJsonPath('message', 'Vehicles retrieved.')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $vehicle->id)
            ->assertJsonPath('data.items.0.owner.id', $owner->id);
    }

    public function test_can_filter_vehicles_by_owner_and_created_dates(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'vehicle.filter.actor@example.com']);
        $owner = $this->owner(['email' => 'vehicle.filter.owner@example.com']);
        $otherOwner = $this->owner(['email' => 'vehicle.filter.other@example.com']);
        $vehicle = $this->vehicleFor($owner, [
            'license_plate' => 'FLT123',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $this->vehicleFor($otherOwner, ['license_plate' => 'FLT999']);

        $url = '/api/v1/vehicles?owner_id='.$owner->id
            .'&created_from='.now()->subDays(2)->toDateString()
            .'&created_to='.now()->toDateString();

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $vehicle->id);
    }

    public function test_technician_cannot_list_vehicles(): void
    {
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.vehicle.index@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicles')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_list_vehicles(): void
    {
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
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
