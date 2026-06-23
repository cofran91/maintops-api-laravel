<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
use App\Models\Audit;
use App\Models\User;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class UserAuditTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    public function test_user_creation_with_role_is_recorded_as_one_business_audit(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'creator.audit@example.com']);

        $createdId = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/users', array_merge($this->payloadFor(SystemRole::Technician), [
                'name' => 'Audited technician',
                'email' => 'audited.technician@example.com',
                'document_number' => 'AUDIT-technician',
            ]))
            ->assertCreated()
            ->json('data.id');

        $audit = Audit::query()
            ->where('event', 'user created')
            ->where('auditable_type', (new User)->getMorphClass())
            ->where('auditable_id', $createdId)
            ->firstOrFail();

        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame([SystemRole::Technician->value], $audit->new_values['roles']);
        $this->assertSame('audited.technician@example.com', $audit->new_values['attributes']['email']);
        $this->assertArrayNotHasKey('password', $audit->new_values['attributes']);
        $this->assertDatabaseCount('audits', 1);
    }

    public function test_user_role_update_is_recorded_with_old_and_new_snapshots(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'updater.audit@example.com']);
        $target = $this->userWithRole(SystemRole::Advisor, ['email' => 'audit.target@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/users/'.$target->id, $this->updatePayloadFor($target, SystemRole::Technician, [
                'name' => 'Audit Target Updated',
            ]))
            ->assertOk();

        $audit = Audit::query()
            ->where('event', 'user updated')
            ->where('auditable_type', $target->getMorphClass())
            ->where('auditable_id', $target->id)
            ->firstOrFail();

        $this->assertSame([SystemRole::Advisor->value], $audit->old_values['roles']);
        $this->assertSame([SystemRole::Technician->value], $audit->new_values['roles']);
        $this->assertSame('Audit Target Updated', $audit->new_values['attributes']['name']);
        $this->assertArrayNotHasKey('password', $audit->old_values['attributes']);
        $this->assertArrayNotHasKey('password', $audit->new_values['attributes']);
    }
}
