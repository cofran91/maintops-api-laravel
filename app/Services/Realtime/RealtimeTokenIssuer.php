<?php

namespace App\Services\Realtime;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class RealtimeTokenIssuer
{
    /**
     * @return array{token: string, token_type: string, expires_in: int, expires_at: string, audience: string}
     *
     * @throws JsonException
     */
    public function issueFor(User $user): array
    {
        $issuedAt = CarbonImmutable::now();
        $ttl = max(1, (int) config('operations.realtime.token_ttl_seconds', 300));
        $expiresAt = $issuedAt->addSeconds($ttl);
        $audience = (string) config('operations.realtime.token_audience', 'realtime');

        $payload = [
            'iss' => (string) config('app.url'),
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
        $secret = (string) config('operations.realtime.token_secret', '');

        if ($secret === '') {
            throw new RuntimeException('Realtime token secret is not configured.');
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
