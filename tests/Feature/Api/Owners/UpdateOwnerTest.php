<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class UpdateOwnerTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('ownerManagerRoleProvider')]
    public function test_allowed_roles_can_update_owner(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, ['email' => $role->value.'.owner.update@example.com']);
        $owner = $this->owner(['email' => 'owner.update@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/owners/'.$owner->id, $this->ownerUpdatePayloadFor($owner, [
                'name' => 'Updated Owner',
                'email' => 'OWNER.UPDATED@EXAMPLE.COM',
                'is_active' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Owner updated successfully.')
            ->assertJsonPath('data.name', 'Updated Owner')
            ->assertJsonPath('data.email', 'owner.updated@example.com')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('owners', [
            'id' => $owner->id,
            'email' => 'owner.updated@example.com',
            'is_active' => false,
        ]);
    }

    public function test_update_requires_full_payload(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'owner.update.validation@example.com']);
        $owner = $this->owner(['email' => 'owner.update.required@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/owners/'.$owner->id, ['name' => 'Partial Owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'is_active']);
    }

    public function test_owner_update_requires_unique_contact_fields(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, ['email' => 'owner.update.unique.actor@example.com']);
        $owner = $this->owner(['email' => 'owner.update.unique.target@example.com']);
        $existing = $this->owner([
            'email' => 'duplicate.update.owner@example.com',
            'document_number' => 'DUP-UPDATE-OWNER',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/owners/'.$owner->id, $this->ownerUpdatePayloadFor($owner, [
                'email' => strtoupper($existing->email),
                'document_number' => $existing->document_number,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'document_number']);
    }

    public function test_technician_cannot_update_owner(): void
    {
        $owner = $this->owner(['email' => 'owner.update.denied.target@example.com']);
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.owner.update@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/owners/'.$owner->id, $this->ownerUpdatePayloadFor($owner, ['name' => 'Denied Owner']))
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_update_owner(): void
    {
        $owner = $this->owner(['email' => 'owner.update.guest@example.com']);

        $this->patchJson('/api/v1/owners/'.$owner->id, $this->ownerUpdatePayloadFor($owner))->assertUnauthorized();
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
