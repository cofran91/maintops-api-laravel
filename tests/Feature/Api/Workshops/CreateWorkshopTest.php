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

class CreateWorkshopTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_create_workshop(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.create@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.created.manager@example.com']);
        $vehicleSystems = $this->vehicleSystems(2);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $vehicleSystems))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Workshop created successfully.')
            ->assertJsonPath('data.manager_user_id', $manager->id)
            ->assertJsonPath('data.manager.id', $manager->id)
            ->assertJsonPath('data.code', 'NORTH-WORKSHOP')
            ->assertJsonPath('data.email', 'north.workshop@example.com')
            ->assertJsonPath('data.weekly_schedule.monday.opens_at', '08:00')
            ->assertJsonCount(2, 'data.vehicle_system_ids');

        $this->assertDatabaseHas('workshops', [
            'id' => $response->json('data.id'),
            'manager_user_id' => $manager->id,
            'code' => 'NORTH-WORKSHOP',
            'email' => 'north.workshop@example.com',
        ]);

        foreach ($vehicleSystems as $vehicleSystem) {
            $this->assertDatabaseHas('vehicle_system_workshop', [
                'workshop_id' => $response->json('data.id'),
                'vehicle_system_id' => $vehicleSystem->id,
            ]);
        }
    }

    public function test_workshop_requires_valid_manager_schedule_and_systems(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.validation.actor@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'workshop.invalid.manager@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', [
                'manager_user_id' => $advisor->id,
                'name' => '',
                'code' => '',
                'weekly_schedule' => [
                    'funday' => ['opens_at' => '17:00', 'closes_at' => '08:00'],
                    'monday' => ['opens_at' => '17:00', 'closes_at' => '08:00', 'note' => 'late'],
                ],
                'vehicle_system_ids' => [],
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'manager_user_id',
                'name',
                'code',
                'weekly_schedule.funday',
                'weekly_schedule.monday',
                'weekly_schedule.monday.closes_at',
                'vehicle_system_ids',
            ]);
    }

    public function test_workshop_requires_unique_name_and_code(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.unique.actor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.unique.manager@example.com']);
        $newManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.unique.new.manager@example.com']);
        $vehicleSystems = $this->vehicleSystems(1);
        $this->workshopFor($manager, $vehicleSystems, [
            'name' => 'Existing Workshop',
            'code' => 'EXISTING-WORKSHOP',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($newManager, $vehicleSystems, [
                'name' => 'Existing Workshop',
                'code' => 'existing workshop',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_system_admin_can_create_workshop_with_technicians(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.create.technicians.actor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.create.technicians.manager@example.com']);
        $firstTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'first.workshop.technician@example.com']);
        $secondTechnician = $this->userWithRole(SystemRole::Technician, ['email' => 'second.workshop.technician@example.com']);

        $createdId = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $this->vehicleSystems(1), [
                'technician_user_ids' => [$firstTechnician->id, $secondTechnician->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.technician_user_ids.0', $firstTechnician->id)
            ->assertJsonPath('data.technician_user_ids.1', $secondTechnician->id)
            ->assertJsonPath('data.technicians.0.id', $firstTechnician->id)
            ->assertJsonPath('data.technicians.1.id', $secondTechnician->id)
            ->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $firstTechnician->id,
            'workshop_id' => $createdId,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $secondTechnician->id,
            'workshop_id' => $createdId,
        ]);
    }

    public function test_workshop_requires_assignable_technicians(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.invalid.technicians.actor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.invalid.technicians.manager@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'workshop.invalid.technician.advisor@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $this->vehicleSystems(1), [
                'technician_user_ids' => [$advisor->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['technician_user_ids.0']);
    }

    public function test_workshop_manager_assigned_to_another_workshop_cannot_create_workshop(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.assigned.manager.actor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.assigned.manager@example.com']);
        $vehicleSystems = $this->vehicleSystems(1);
        $this->workshopFor($manager, $vehicleSystems, [
            'name' => 'Assigned Manager Workshop',
            'code' => 'ASSIGNED-MANAGER-WORKSHOP',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $vehicleSystems, [
                'name' => 'Second Manager Workshop',
                'code' => 'second-manager-workshop',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manager_user_id']);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_non_admin_roles_cannot_create_workshop(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.create.denied@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.workshop.denied.manager@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $this->vehicleSystems(1)))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_create_workshop(): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'guest.workshop.manager@example.com']);

        $this->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $this->vehicleSystems(1)))
            ->assertUnauthorized();
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
