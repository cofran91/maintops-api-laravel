<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class CreateOwnerTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('ownerManagerRoleProvider')]
    public function test_allowed_roles_can_create_owner(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.owner.create@example.com']);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/owners', $this->ownerPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Owner created successfully.')
            ->assertJsonPath('data.name', 'Owner 1')
            ->assertJsonPath('data.email', 'owner.1@example.com')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('owners', [
            'id' => $response->json('data.id'),
            'email' => 'owner.1@example.com',
            'document_number' => 'OWNER-DOC-1',
        ]);
    }

    public function test_owner_requires_unique_contact_fields(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'owner.validation.actor@example.com']);
        $existing = $this->owner([
            'email' => 'duplicate.owner@example.com',
            'document_number' => 'DUP-OWNER',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/owners', [
                'name' => '',
                'email' => strtoupper($existing->email),
                'document_number' => $existing->document_number,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'is_active', 'document_number']);
    }

    public function test_technician_cannot_create_owner(): void
    {
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.owner.create@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/owners', $this->ownerPayload())
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_create_owner(): void
    {
        $this->postJson('/api/v1/owners', $this->ownerPayload())->assertUnauthorized();
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
