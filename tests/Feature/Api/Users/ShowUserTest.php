<?php

namespace Tests\Feature\Api\Users;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ShowUserTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    #[DataProvider('systemAdminProvider')]
    public function test_system_admin_can_show_user(SystemRole $actorRole): void
    {
        $actor = $this->userWithRole($actorRole, ['email' => $actorRole->value.'.viewer@example.com']);
        $target = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'super-admin.show.target@example.com']);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);
    }

    public function test_workshop_manager_can_show_technician_only(): void
    {
        $workshopManager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.manager.show@example.com']);
        $technician = $this->userWithRole(SystemRole::Technician, ['email' => 'technician.show.target@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.show.target@example.com']);
        $token = $workshopManager->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/users/'.$technician->id)
            ->assertOk()
            ->assertJsonPath('data.id', $technician->id);

        $this->withToken($token)
            ->getJson('/api/v1/users/'.$advisor->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_show_user(): void
    {
        $target = $this->userWithRole(SystemRole::Technician, ['email' => 'guest-target@example.com']);

        $this->getJson('/api/v1/users/'.$target->id)->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function systemAdminProvider(): iterable
    {
        yield 'super admin' => [SystemRole::SuperAdmin];
        yield 'admin' => [SystemRole::Admin];
    }
}
