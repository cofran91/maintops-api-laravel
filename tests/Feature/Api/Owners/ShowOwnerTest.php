<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ShowOwnerTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('ownerManagerRoleProvider')]
    public function test_allowed_roles_can_show_owner(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.owner.show@example.com']);
        $owner = $this->owner(['email' => 'shown.owner@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('message', 'Owner retrieved.')
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.email', $owner->email);
    }

    public function test_technician_cannot_show_owner(): void
    {
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.owner.show@example.com']);
        $owner = $this->owner(['email' => 'owner.show.target@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners/'.$owner->id)
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_show_owner(): void
    {
        $owner = $this->owner(['email' => 'owner.show.guest@example.com']);

        $this->getJson('/api/v1/owners/'.$owner->id)->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function ownerManagerRoleProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
    }
}
