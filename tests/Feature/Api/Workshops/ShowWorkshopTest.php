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

class ShowWorkshopTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_show_workshop(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.show@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.show.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(2), [
            'name' => 'Visible Workshop',
            'code' => 'VISIBLE-WORKSHOP',
        ]);
        $technician = $this->userWithRole(SystemRole::Technician, [
            'email' => $role->value.'.visible.workshop.technician@example.com',
            'workshop_id' => $workshop->id,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/workshops/'.$workshop->id)
            ->assertOk()
            ->assertJsonPath('message', 'Workshop retrieved.')
            ->assertJsonPath('data.id', $workshop->id)
            ->assertJsonPath('data.manager.id', $manager->id)
            ->assertJsonPath('data.technician_user_ids.0', $technician->id)
            ->assertJsonPath('data.technicians.0.email', $technician->email)
            ->assertJsonCount(2, 'data.vehicle_system_ids');
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_non_admin_roles_cannot_show_workshop(SystemRole $role): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.show.denied.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.show.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/workshops/'.$workshop->id)
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_show_workshop(): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'guest.show.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));

        $this->getJson('/api/v1/workshops/'.$workshop->id)->assertUnauthorized();
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
