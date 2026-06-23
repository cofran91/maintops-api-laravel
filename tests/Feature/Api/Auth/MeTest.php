<?php

namespace Tests\Feature\Api\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->admin = User::query()->where('email', 'admin@maint.test')->firstOrFail();
    }

    public function test_authenticated_user_can_get_current_profile(): void
    {
        $plainTextToken = $this->admin->createToken('feature-test')->plainTextToken;

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Authenticated user.')
            ->assertJsonPath('data.email', 'admin@maint.test')
            ->assertJsonPath('data.roles.0', SystemRole::SuperAdmin->value);
    }

    public function test_guest_cannot_get_current_profile(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
