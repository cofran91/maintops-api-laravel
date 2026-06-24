<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
use App\Models\Workshop;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admin_can_create_allowed_roles(SystemRole $actorRole, SystemRole $createdRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.creator@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', $this->payloadFor($createdRole))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('data.email', $createdRole->value.'.1@example.com')
            ->assertJsonPath('data.roles.0', $createdRole->value);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_no_system_admin_can_create_super_admin(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.super.creator@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', $this->payloadFor(SystemRole::SuperAdmin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_create_users(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.actor@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', $this->payloadFor(SystemRole::Technician))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_create_user(): void
    {
        $this->postJson('/api/v1/users', $this->payloadFor(SystemRole::Technician))->assertUnauthorized();
    }

    public function test_system_admin_can_create_technician_assigned_to_workshop(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.technician.workshop@example.com']);
        $workshop = Workshop::factory()->create();

        $createdId = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', $this->payloadFor(SystemRole::Technician, attributes: [
                'email' => 'assigned.technician@example.com',
                'document_number' => 'ASSIGNED-TECHNICIAN',
                'workshop_id' => $workshop->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.workshop_id', $workshop->id)
            ->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $createdId,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_only_technicians_can_be_assigned_to_workshop(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.non.technician.workshop@example.com']);
        $workshop = Workshop::factory()->create();

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', $this->payloadFor(SystemRole::Advisor, attributes: [
                'email' => 'advisor.with.workshop@example.com',
                'document_number' => 'ADVISOR-WORKSHOP',
                'workshop_id' => $workshop->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workshop_id']);
    }

    /**
     * @return iterable<string, array{SystemRole, SystemRole}>
     */
    public static function systemAdminRoleProvider(): iterable
    {
        foreach ([SystemRole::SuperAdmin, SystemRole::Admin] as $actorRole) {
            foreach ([SystemRole::Admin, SystemRole::WorkshopManager, SystemRole::Advisor, SystemRole::Technician] as $createdRole) {
                yield $actorRole->value.' creates '.$createdRole->value => [$actorRole, $createdRole];
            }
        }
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function systemAdminProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonManagingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
