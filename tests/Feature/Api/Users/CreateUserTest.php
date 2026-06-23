<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
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
