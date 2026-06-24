<?php

namespace Tests\Feature\Api\Workshops;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Workshops\Concerns\InteractsWithWorkshops;
use Tests\TestCase;

class ListWorkshopsTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_list_filtered_workshops(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.index@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.workshop.manager@example.com']);
        [$engine, $brakes] = $this->vehicleSystems(2);
        $workshop = $this->workshopFor($manager, [$engine, $brakes], [
            'name' => 'North Workshop',
            'code' => 'NORTH-WORKSHOP',
            'city' => 'Bogota',
        ]);
        $this->workshopFor($manager, [$brakes], [
            'name' => 'South Workshop',
            'code' => 'SOUTH-WORKSHOP',
            'city' => 'Cali',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/workshops?search=North&vehicle_system_id='.$engine->id)
            ->assertOk()
            ->assertJsonPath('message', 'Workshops retrieved.')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $workshop->id)
            ->assertJsonPath('data.items.0.manager.id', $manager->id)
            ->assertJsonPath('data.items.0.vehicle_system_ids.0', $engine->id);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_non_admin_roles_cannot_list_workshops(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.index.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/workshops')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_list_workshops(): void
    {
        $this->getJson('/api/v1/workshops')->assertUnauthorized();
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
    public static function nonAdminRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
