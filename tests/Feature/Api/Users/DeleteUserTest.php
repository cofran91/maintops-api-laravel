<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class DeleteUserTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_delete_user(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.deleter@example.com']);
        $target = $this->userWithRole(SystemRole::Technician, ['email' => 'deleted-tech@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_delete_super_admin(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.super.deleter@example.com']);
        $target = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'super-admin.delete.target@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_user_cannot_delete_self(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.self.delete@example.com']);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/users/'.$admin->id)
            ->assertForbidden();
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_delete_users(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.delete.guard@example.com']);
        $target = $this->userWithRole(SystemRole::Technician, ['email' => $role->value.'.delete.target@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/users/'.$target->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_delete_user(): void
    {
        $target = $this->userWithRole(SystemRole::Technician, ['email' => 'guest-target@example.com']);

        $this->deleteJson('/api/v1/users/'.$target->id)->assertUnauthorized();
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
