<?php

namespace Tests\Feature\Api\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'operations.service_tokens.secret' => 'testing-service-token-secret-12345',
            'operations.service_tokens.ttl_seconds' => 120,
            'operations.service_tokens.issuer' => 'http://maintops.test',
        ]);

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->admin = User::query()->where('email', 'admin@maint.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_issue_realtime_service_token(): void
    {
        $now = CarbonImmutable::parse('2026-06-25 12:00:00');
        CarbonImmutable::setTestNow($now);

        $response = $this->withToken($this->admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/auth/service-token', ['audience' => 'realtime'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Service token issued.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 120)
            ->assertJsonPath('data.audience', 'realtime')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_in',
                    'expires_at',
                    'audience',
                ],
            ]);

        $payload = $this->decodePayload($response->json('data.token'));

        $this->assertSignedToken($response->json('data.token'));
        $this->assertSame((string) $this->admin->id, $payload['sub']);
        $this->assertSame('realtime', $payload['aud']);
        $this->assertSame([SystemRole::SuperAdmin->value], $payload['roles']);
        $this->assertNull($payload['workshop_id']);
        $this->assertSame($now->getTimestamp(), $payload['iat']);
        $this->assertSame($now->addSeconds(120)->getTimestamp(), $payload['exp']);
        $this->assertIsString($payload['jti']);
        $this->assertSame('http://maintops.test', $payload['iss']);
    }

    public function test_administrator_can_issue_analytics_service_token(): void
    {
        $response = $this->withToken($this->admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/auth/service-token', ['audience' => 'analytics'])
            ->assertOk()
            ->assertJsonPath('data.audience', 'analytics');

        $payload = $this->decodePayload($response->json('data.token'));

        $this->assertSame('analytics', $payload['aud']);
        $this->assertSame((string) $this->admin->id, $payload['sub']);
    }

    #[DataProvider('workshopScopeProvider')]
    public function test_realtime_service_token_includes_user_workshop_scope(string $role, string $scopeSource): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $workshop = Workshop::factory()->create([
            'manager_user_id' => $scopeSource === 'managed' ? $user->id : User::factory(),
        ]);

        if ($scopeSource === 'assigned') {
            $user->forceFill(['workshop_id' => $workshop->id])->save();
        }

        $response = $this->withToken($user->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/auth/service-token', ['audience' => 'realtime'])
            ->assertOk();

        $payload = $this->decodePayload($response->json('data.token'));

        $this->assertSame((string) $user->id, $payload['sub']);
        $this->assertSame([$role], $payload['roles']);
        $this->assertSame($workshop->id, $payload['workshop_id']);
    }

    public function test_technician_cannot_issue_analytics_service_token(): void
    {
        $technician = User::factory()->create();
        $technician->assignRole(SystemRole::Technician->value);

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/auth/service-token', ['audience' => 'analytics'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Not authorized to issue Analytics tokens.');
    }

    public function test_invalid_audience_is_rejected(): void
    {
        $this->withToken($this->admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/auth/service-token', ['audience' => 'notifications'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audience']);
    }

    public function test_guest_cannot_issue_service_token(): void
    {
        $this->postJson('/api/v1/auth/service-token', ['audience' => 'realtime'])
            ->assertUnauthorized();
    }

    /**
     * @return array<string, array{role: string, scopeSource: string}>
     */
    public static function workshopScopeProvider(): array
    {
        return [
            'workshop manager uses managed workshop' => [
                'role' => SystemRole::WorkshopManager->value,
                'scopeSource' => 'managed',
            ],
            'technician uses assigned workshop' => [
                'role' => SystemRole::Technician->value,
                'scopeSource' => 'assigned',
            ],
        ];
    }

    private function assertSignedToken(string $token): void
    {
        $parts = explode('.', $token);

        $this->assertCount(3, $parts);

        $unsignedToken = $parts[0].'.'.$parts[1];
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $unsignedToken, 'testing-service-token-secret-12345', true),
        );

        $this->assertSame($expectedSignature, $parts[2]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $token): array
    {
        $parts = explode('.', $token);

        $this->assertCount(3, $parts);

        return json_decode($this->base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
