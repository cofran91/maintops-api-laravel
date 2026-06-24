<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ListOwnersTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('ownerManagerRoleProvider')]
    public function test_allowed_roles_can_list_filtered_owners(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.owner.index@example.com']);
        $owner = $this->owner([
            'name' => 'Maria Owner',
            'email' => 'maria.owner@example.com',
            'document_number' => 'MARIA-OWNER',
            'is_active' => true,
        ]);
        $this->owner([
            'name' => 'Carlos Owner',
            'email' => 'carlos.owner@example.com',
            'is_active' => true,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners?search=Maria&is_active=true')
            ->assertOk()
            ->assertJsonPath('message', 'Owners retrieved.')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $owner->id);
    }

    public function test_technician_cannot_list_owners(): void
    {
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.owner.index@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_inactive_actor_cannot_list_owners(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'inactive.owner.index@example.com',
            'is_active' => false,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_list_owners(): void
    {
        $this->getJson('/api/v1/owners')->assertUnauthorized();
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
