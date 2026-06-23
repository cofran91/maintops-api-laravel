<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_update_user(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.updater@example.com']);
        $target = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.manager.target@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/users/'.$target->id, $this->updatePayloadFor($target, SystemRole::Technician, [
                'name' => 'Updated Technician',
                'email' => 'updated-technician@example.com',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
                'phone' => '+57 300 999 9999',
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'User updated successfully.')
            ->assertJsonPath('data.name', 'Updated Technician')
            ->assertJsonPath('data.email', 'updated-technician@example.com')
            ->assertJsonPath('data.roles.0', SystemRole::Technician->value)
            ->assertJsonPath('data.phone', '+57 300 999 9999');

        $updatedUser = User::query()->findOrFail($target->id);

        $this->assertTrue(Hash::check('new-password', $updatedUser->password));
        $this->assertTrue($updatedUser->hasRole(SystemRole::Technician->value));
        $this->assertFalse($updatedUser->hasRole(SystemRole::WorkshopManager->value));
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_update_super_admin_profile(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.super.updater@example.com']);
        $target = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'target.super.update@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/users/'.$target->id, $this->updatePayloadFor($target, SystemRole::SuperAdmin, [
                'name' => 'Updated Super Admin',
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Super Admin')
            ->assertJsonPath('data.roles.0', SystemRole::SuperAdmin->value);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_cannot_promote_user_to_super_admin(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.role.guard@example.com']);
        $lowerLevel = $this->userWithRole(SystemRole::Technician, ['email' => 'lower-tech@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/users/'.$lowerLevel->id, $this->updatePayloadFor($lowerLevel, SystemRole::SuperAdmin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_update_requires_full_payload(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.required.update@example.com']);
        $technician = $this->userWithRole(SystemRole::Technician, ['email' => 'required.update.tech@example.com']);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/users/'.$technician->id, [
                'name' => 'Partial Update',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'role', 'is_active']);
    }

    #[DataProvider('nonManagingRoleProvider')]
    public function test_non_admin_roles_cannot_update_user(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.update.guard@example.com']);
        $technician = $this->userWithRole(SystemRole::Technician, ['email' => $role->value.'.tech.update.guard@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/users/'.$technician->id, $this->updatePayloadFor($technician, SystemRole::Technician, [
                'name' => 'Not allowed',
            ]))
            ->assertForbidden();
    }

    public function test_guest_cannot_update_user(): void
    {
        $target = $this->userWithRole(SystemRole::Technician, ['email' => 'guest-target@example.com']);

        $this->patchJson('/api/v1/users/'.$target->id, ['name' => 'Nope'])->assertUnauthorized();
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
