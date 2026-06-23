<?php

namespace Tests\Feature\Api\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->admin = User::query()->where('email', 'admin@maint.test')->firstOrFail();
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maint.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'admin@maint.test')
            ->assertJsonPath('data.user.roles.0', SystemRole::SuperAdmin->value)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->admin->id,
            'name' => 'api-token',
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maint.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deleted_user_cannot_login(): void
    {
        $this->admin->delete();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maint.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->admin->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maint.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
