<?php

namespace App\Services\ServiceTokens;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class ServiceTokenIssuer
{
    /**
     * @return array{token: string, token_type: string, expires_in: int, expires_at: string, audience: string}
     *
     * @throws JsonException
     */
    public function issueFor(User $user, string $audience): array
    {
        $issuedAt = CarbonImmutable::now();
        $ttl = max(1, (int) config('operations.service_tokens.ttl_seconds', 300));
        $expiresAt = $issuedAt->addSeconds($ttl);

        $payload = [
            'iss' => (string) config('operations.service_tokens.issuer', config('app.url')),
            'aud' => $audience,
            'sub' => (string) $user->getKey(),
            'jti' => (string) Str::uuid(),
            'roles' => $user->getRoleNames()->values()->all(),
            'workshop_id' => $this->workshopId($user),
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ];

        return [
            'token' => $this->sign($payload),
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'expires_at' => $expiresAt->toISOString(),
            'audience' => $audience,
        ];
    }

    private function workshopId(User $user): ?int
    {
        if ($user->workshop_id !== null) {
            return (int) $user->workshop_id;
        }

        $managedWorkshopId = $user->managedWorkshop()->value('id');

        return $managedWorkshopId === null ? null : (int) $managedWorkshopId;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function sign(array $payload): string
    {
        $secret = (string) config('operations.service_tokens.secret', '');

        if (strlen($secret) < 32) {
            throw new RuntimeException('Service token secret must contain at least 32 characters.');
        }

        $unsignedToken = implode('.', [
            $this->jsonSegment([
                'typ' => 'JWT',
                'alg' => 'HS256',
            ]),
            $this->jsonSegment($payload),
        ]);

        return $unsignedToken.'.'.$this->base64UrlEncode(
            hash_hmac('sha256', $unsignedToken, $secret, true),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    private function jsonSegment(array $data): string
    {
        return $this->base64UrlEncode(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
