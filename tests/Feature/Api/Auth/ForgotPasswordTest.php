<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->admin = User::query()->where('email', 'admin@maint.test')->firstOrFail();
    }

    public function test_user_can_request_password_recovery_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $this->admin->email,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a password reset link will be sent.');

        Notification::assertSentTo(
            $this->admin,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => $notification->queue === 'mail',
        );
    }

    public function test_unknown_email_receives_public_success_response_without_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@maint.test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a password reset link will be sent.');

        Notification::assertNothingSent();
    }

    public function test_inactive_user_receives_public_success_response_without_notification(): void
    {
        Notification::fake();

        $this->admin->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $this->admin->email,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a password reset link will be sent.');

        Notification::assertNothingSent();
    }

    public function test_email_is_required_to_request_password_recovery(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
