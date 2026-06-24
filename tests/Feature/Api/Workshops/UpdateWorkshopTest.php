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

class UpdateWorkshopTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_update_workshop(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.update@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.update.manager@example.com']);
        [$engine, $brakes, $electrical] = $this->vehicleSystems(3);
        $workshop = $this->workshopFor($manager, [$engine, $brakes], [
            'name' => 'Update Workshop',
            'code' => 'UPDATE-WORKSHOP',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop, [
                'name' => 'Updated Workshop',
                'code' => 'updated workshop',
                'city' => 'Medellin',
                'weekly_schedule' => [
                    'Friday' => ['opens_at' => '09:00', 'closes_at' => '15:00'],
                ],
                'vehicle_system_ids' => [$electrical->id],
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Workshop updated successfully.')
            ->assertJsonPath('data.name', 'Updated Workshop')
            ->assertJsonPath('data.code', 'UPDATED-WORKSHOP')
            ->assertJsonPath('data.city', 'Medellin')
            ->assertJsonPath('data.weekly_schedule.friday.opens_at', '09:00')
            ->assertJsonCount(1, 'data.vehicle_system_ids')
            ->assertJsonPath('data.vehicle_system_ids.0', $electrical->id);

        $this->assertDatabaseHas('workshops', [
            'id' => $workshop->id,
            'name' => 'Updated Workshop',
            'code' => 'UPDATED-WORKSHOP',
            'city' => 'Medellin',
        ]);
        $this->assertDatabaseHas('vehicle_system_workshop', [
            'workshop_id' => $workshop->id,
            'vehicle_system_id' => $electrical->id,
        ]);
        $this->assertDatabaseMissing('vehicle_system_workshop', [
            'workshop_id' => $workshop->id,
            'vehicle_system_id' => $engine->id,
        ]);
    }

    public function test_update_requires_full_payload(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.update.validation@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.update.validation.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, ['city' => 'Medellin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'manager_user_id',
                'name',
                'code',
                'weekly_schedule',
                'vehicle_system_ids',
                'technician_user_ids',
                'is_active',
            ]);
    }

    public function test_workshop_update_requires_valid_manager_schedule_systems_and_unique_code(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.update.invalid.actor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.update.invalid.manager@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'workshop.update.invalid.advisor@example.com']);
        $vehicleSystems = $this->vehicleSystems(1);
        $workshop = $this->workshopFor($manager, $vehicleSystems, ['code' => 'CURRENT-WORKSHOP']);
        $this->workshopFor($manager, $vehicleSystems, ['code' => 'DUPLICATE-WORKSHOP']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop, [
                'manager_user_id' => $advisor->id,
                'code' => 'duplicate workshop',
                'weekly_schedule' => [
                    'monday' => ['opens_at' => '18:00', 'closes_at' => '08:00'],
                ],
                'vehicle_system_ids' => [],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'manager_user_id',
                'code',
                'weekly_schedule.monday.closes_at',
                'vehicle_system_ids',
            ]);
    }

    public function test_system_admin_can_replace_workshop_technicians(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.replace.technicians.actor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.replace.technicians.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1), [
            'name' => 'Technician Replace Workshop',
            'code' => 'TECHNICIAN-REPLACE-WORKSHOP',
        ]);
        $oldTechnician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'old.workshop.technician@example.com',
            'workshop_id' => $workshop->id,
        ]);
        $newTechnician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'new.workshop.technician@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop, [
                'technician_user_ids' => [$newTechnician->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.technician_user_ids.0', $newTechnician->id)
            ->assertJsonPath('data.technicians.0.id', $newTechnician->id);

        $this->assertDatabaseHas('users', [
            'id' => $oldTechnician->id,
            'workshop_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $newTechnician->id,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_workshop_cannot_assign_technician_from_another_workshop(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.foreign.technician.actor@example.com']);
        $northManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'north.foreign.technician.manager@example.com']);
        $southManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'south.foreign.technician.manager@example.com']);
        $northWorkshop = $this->workshopFor($northManager, $this->vehicleSystems(1), [
            'name' => 'North Technician Workshop',
            'code' => 'NORTH-TECHNICIAN-WORKSHOP',
        ]);
        $southWorkshop = $this->workshopFor($southManager, $this->vehicleSystems(1), [
            'name' => 'South Technician Workshop',
            'code' => 'SOUTH-TECHNICIAN-WORKSHOP',
        ]);
        $technician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'foreign.workshop.technician@example.com',
            'workshop_id' => $northWorkshop->id,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$southWorkshop->id, $this->workshopUpdatePayloadFor($southWorkshop, [
                'technician_user_ids' => [$technician->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['technician_user_ids.0']);
    }

    public function test_workshop_cannot_be_reassigned_to_manager_assigned_to_another_workshop(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'workshop.reassign.actor@example.com']);
        $currentManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.current.manager@example.com']);
        $assignedManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.already.assigned.manager@example.com']);
        $vehicleSystems = $this->vehicleSystems(1);
        $workshop = $this->workshopFor($currentManager, $vehicleSystems, [
            'name' => 'Current Manager Workshop',
            'code' => 'CURRENT-MANAGER-WORKSHOP',
        ]);
        $this->workshopFor($assignedManager, $vehicleSystems, [
            'name' => 'Already Assigned Manager Workshop',
            'code' => 'ALREADY-ASSIGNED-MANAGER-WORKSHOP',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop, [
                'manager_user_id' => $assignedManager->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manager_user_id']);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_non_admin_roles_cannot_update_workshop(SystemRole $role): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => $role->value.'.update.denied.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));
        $actor = $this->userWithRole($role, ['email' => $role->value.'.workshop.update.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop, ['city' => 'Cali']))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_update_workshop(): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'guest.update.manager@example.com']);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(1));

        $this->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop))
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
