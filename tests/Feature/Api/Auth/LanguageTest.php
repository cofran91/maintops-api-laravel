<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->admin = User::query()->where('email', 'admin@maint.test')->firstOrFail();
    }

    public function test_authenticated_user_can_change_language(): void
    {
        $plainTextToken = $this->admin->createToken('feature-test')->plainTextToken;

        $this->withToken($plainTextToken)
            ->patchJson('/api/v1/auth/language', [
                'locale' => 'ES_co',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Idioma actualizado correctamente.')
            ->assertJsonPath('data.locale', 'es');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'preferred_locale' => 'es',
        ]);

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('message', 'Usuario autenticado.')
            ->assertJsonPath('data.preferred_locale', 'es');
    }

    public function test_guest_exception_uses_requested_language_header(): void
    {
        $this->withHeader('X-Locale', 'es')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No autenticado.');
    }

    public function test_validation_errors_use_authenticated_user_language(): void
    {
        $this->admin->forceFill(['preferred_locale' => 'es'])->save();

        $plainTextToken = $this->admin->createToken('feature-test')->plainTextToken;

        $this->withToken($plainTextToken)
            ->patchJson('/api/v1/auth/language', [
                'locale' => 'fr',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Los datos proporcionados no son válidos.')
            ->assertJsonValidationErrors(['locale'])
            ->assertJsonPath('errors.locale.0', 'El idioma seleccionado no es válido.');
    }
}
