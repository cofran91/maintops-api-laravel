<?php

namespace Tests\Feature\Api\Audits;

use App\Enums\SystemRole;
use App\Models\Audit;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ListAuditsTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    public function test_super_admin_can_list_filtered_audits(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'audit.viewer@example.com']);
        $auditable = $this->userWithRole(SystemRole::Advisor, ['email' => 'audited.advisor@example.com']);

        $matching = Audit::query()->create([
            'user_type' => $actor->getMorphClass(),
            'user_id' => $actor->id,
            'event' => 'user updated',
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->id,
            'old_values' => ['roles' => [SystemRole::Advisor->value]],
            'new_values' => ['roles' => [SystemRole::Technician->value]],
            'url' => 'http://localhost/api/v1/users/'.$auditable->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature Test',
            'tags' => 'users',
        ]);

        Audit::query()->create([
            'event' => 'created',
            'auditable_type' => $actor->getMorphClass(),
            'auditable_id' => $actor->id,
            'old_values' => [],
            'new_values' => ['name' => 'Other'],
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/audits?event='.urlencode('user updated').'&auditable_type='.urlencode($auditable->getMorphClass()))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Audits retrieved.')
            ->assertJsonPath('data.items.0.id', $matching->id)
            ->assertJsonPath('data.items.0.event', 'user updated')
            ->assertJsonPath('data.items.0.actor.id', $actor->id)
            ->assertJsonPath('data.items.0.auditable.id', $auditable->id)
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_non_super_admin_cannot_list_audits(): void
    {
        $actor = $this->userWithRole(SystemRole::Technician, ['email' => 'audit.denied@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/audits')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_list_audits(): void
    {
        $this->getJson('/api/v1/audits')->assertUnauthorized();
    }
}
