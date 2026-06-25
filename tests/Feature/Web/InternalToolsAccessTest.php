<?php

namespace Tests\Feature\Web;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class InternalToolsAccessTest extends TestCase
{
    use InteractsWithUsers, RefreshDatabase;

    private bool $openApiFileExisted = false;

    private ?string $originalOpenApiFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->rememberExportedOpenApiFile();
        $this->ensureExportedOpenApiFileExists();
    }

    protected function tearDown(): void
    {
        $this->restoreExportedOpenApiFile();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_internal_tools_login_for_docs(): void
    {
        $this->get('/docs/api.json')
            ->assertRedirect(route('internal-tools.login'));
    }

    public function test_web_login_allows_only_active_super_admin_users(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, [
            'email' => 'advisor.console@example.com',
            'password' => 'password',
        ]);

        $this->post('/admin/login', [
            'email' => $advisor->email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_web_session_can_access_docs_json(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdmin, [
            'email' => 'super-admin.console@example.com',
            'password' => 'password',
        ]);

        $this->post('/admin/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('docs'));

        $this->get('/docs/api.json')
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_non_super_admin_web_session_cannot_access_docs_json(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'advisor.web.docs@example.com']);

        $this->actingAs($advisor)
            ->get('/docs/api.json')
            ->assertForbidden();
    }

    public function test_telescope_gate_allows_only_super_admin_users(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'super-admin.telescope@example.com']);
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.telescope@example.com']);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($admin)->allows('viewTelescope'));
    }

    private function ensureExportedOpenApiFileExists(): void
    {
        file_put_contents(
            public_path('api.json'),
            json_encode([
                'openapi' => '3.1.0',
                'info' => [
                    'title' => 'MaintOps Test API',
                    'version' => '1.0.0',
                ],
                'paths' => [],
            ], JSON_THROW_ON_ERROR),
        );
    }

    private function rememberExportedOpenApiFile(): void
    {
        $path = public_path('api.json');

        $this->openApiFileExisted = is_file($path);
        $this->originalOpenApiFile = $this->openApiFileExisted
            ? file_get_contents($path)
            : null;
    }

    private function restoreExportedOpenApiFile(): void
    {
        $path = public_path('api.json');

        if ($this->openApiFileExisted) {
            file_put_contents($path, $this->originalOpenApiFile ?? '');

            return;
        }

        if (is_file($path)) {
            unlink($path);
        }
    }
}
