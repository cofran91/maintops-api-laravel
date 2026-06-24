<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class DeleteOwnerTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminRoleProvider')]
    public function test_system_admins_can_delete_owner(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.owner.delete@example.com']);
        $owner = $this->owner(['email' => 'owner.delete@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/owners/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('message', 'Owner deleted successfully.');

        $this->assertSoftDeleted('owners', ['id' => $owner->id]);
    }

    #[DataProvider('nonDeletingRoleProvider')]
    public function test_non_admin_roles_cannot_delete_owner(SystemRole $role): void
    {
        $owner = $this->owner(['email' => 'owner.delete.denied.target@example.com']);
        $actor = $this->userWithRole($role, ['email' => $role->value.'.owner.delete.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->deleteJson('/api/v1/owners/'.$owner->id)
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_delete_owner(): void
    {
        $owner = $this->owner(['email' => 'owner.delete.guest@example.com']);

        $this->deleteJson('/api/v1/owners/'.$owner->id)->assertUnauthorized();
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
    public static function nonDeletingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }
}
