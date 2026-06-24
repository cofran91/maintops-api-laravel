<?php

namespace Tests\Feature\Api\VehicleSystems;

use App\Enums\SystemRole;
use App\Enums\VehicleSystemCode;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ListVehicleSystemsTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('authenticatedRoleProvider')]
    public function test_authenticated_roles_can_list_vehicle_system_catalog(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.systems.viewer@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicle-systems?code='.VehicleSystemCode::Engine->value)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle systems retrieved.')
            ->assertJsonPath('data.items.0.code', VehicleSystemCode::Engine->value)
            ->assertJsonPath('data.items.0.name', VehicleSystemCode::Engine->label());
    }

    public function test_can_filter_vehicle_systems_by_search(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'systems.searcher@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicle-systems?search=brak')
            ->assertOk()
            ->assertJsonPath('data.items.0.code', VehicleSystemCode::Brakes->value)
            ->assertJsonPath('data.items.0.name', VehicleSystemCode::Brakes->label());
    }

    public function test_vehicle_system_catalog_requires_authentication(): void
    {
        $this->getJson('/api/v1/vehicle-systems')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function authenticatedRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
