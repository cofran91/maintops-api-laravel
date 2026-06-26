<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->admin = User::query()->where('email', 'admin@maint.test')->firstOrFail();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $this->admin->createToken('old-token');
        $token = Password::broker()->createToken($this->admin);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password updated successfully.');

        $this->admin->refresh();

        $this->assertTrue(Hash::check('new-password', $this->admin->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->admin->id,
        ]);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $this->admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inactive_user_cannot_reset_password(): void
    {
        $token = Password::broker()->createToken($this->admin);

        $this->admin->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->admin->refresh();

        $this->assertTrue(Hash::check('password', $this->admin->password));
    }

    public function test_reset_password_requires_confirmed_password(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'any-token',
            'email' => $this->admin->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
