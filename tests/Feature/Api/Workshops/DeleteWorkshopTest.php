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

class DeleteWorkshopTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_delete_workshop(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.delete@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.delete.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/workshops/'.$workshop->id)
            ->assertOk()
            ->assertJsonPath('message', 'Workshop deleted successfully.');

        $this->assertSoftDeleted('workshops', ['id' => $workshop->id]);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_non_admin_roles_cannot_delete_workshop(SystemRole $role): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.delete.denied.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.delete.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/workshops/'.$workshop->id)
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_delete_workshop(): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'guest.delete.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));

        $this->deleteJson('/api/v1/workshops/'.$workshop->id)->assertUnauthorized();
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
