<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
use App\Models\Workshop;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    public function test_index_is_limited_by_hierarchy(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.viewer@example.com']);
        $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'hidden-super@example.com']);
        $this->userWithRole(SystemRole::Admin, ['email' => 'visible-admin@example.com']);
        $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'visible-workshop-manager@example.com']);
        $this->userWithRole(SystemRole::Advisor, ['email' => 'visible-advisor@example.com']);
        $this->userWithRole(SystemRole::Technician, ['email' => 'visible-technician@example.com']);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'hidden-super@example.com'])
            ->assertJsonFragment(['email' => 'visible-admin@example.com'])
            ->assertJsonFragment(['email' => 'visible-workshop-manager@example.com'])
            ->assertJsonFragment(['email' => 'visible-advisor@example.com'])
            ->assertJsonFragment(['email' => 'visible-technician@example.com']);
    }

    public function test_can_filter_users_by_search(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'super-admin@example.com']);

        $createdId = $this->withToken($superAdmin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', $this->payloadFor(SystemRole::WorkshopManager))
            ->assertCreated()
            ->json('data.id');

        $this->withToken($superAdmin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users?search=workshop_manager.1')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $createdId)
            ->assertJsonPath('data.items.0.roles.0', SystemRole::WorkshopManager->value);
    }

    public function test_can_filter_users_by_role_and_active_state(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'super-admin.filters@example.com']);
        $activeTechnician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'active.technician@example.com',
            'is_active' => true,
        ]);
        $inactiveTechnician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'inactive.technician@example.com',
            'is_active' => false,
        ]);
        $this->userWithRole(SystemRole::Advisor, ['email' => 'hidden.advisor@example.com']);

        $this->withToken($superAdmin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users?role=technician&is_active=true')
            ->assertOk()
            ->assertJsonFragment(['email' => $activeTechnician->email])
            ->assertJsonMissing(['email' => $inactiveTechnician->email])
            ->assertJsonMissing(['email' => 'hidden.advisor@example.com']);
    }

    public function test_can_filter_workshop_managers_without_workshop(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'super-admin.workshop.filter@example.com']);
        $assignedManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'assigned.manager@example.com']);
        $unassignedManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'unassigned.manager@example.com']);
        Workshop::factory()->create(['manager_user_id' => $assignedManager->id]);

        $this->withToken($superAdmin->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users?role=workshop_manager&without_workshop=true')
            ->assertOk()
            ->assertJsonFragment(['email' => $unassignedManager->email])
            ->assertJsonMissing(['email' => $assignedManager->email]);
    }

    public function test_workshop_manager_lists_technicians_only(): void
    {
        $workshopManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.manager.viewer@example.com']);
        $this->userWithRole(SystemRole::Advisor, ['email' => 'hidden-advisor@example.com']);
        $this->userWithRole(SystemRole::Technician, ['email' => 'visible-tech@example.com']);

        $this->withToken($workshopManager->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'visible-tech@example.com'])
            ->assertJsonMissing(['email' => 'hidden-advisor@example.com']);
    }

    #[DataProvider('nonListingRoleProvider')]
    public function test_advisor_and_technician_cannot_list_users(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.viewer@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonListingRoleProvider(): iterable
    {
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
