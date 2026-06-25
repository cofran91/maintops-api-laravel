<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\SystemRole;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\ServiceTokenRequest;
use App\Models\User;
use App\Services\ServiceTokens\ServiceTokenIssuer;
use Illuminate\Http\JsonResponse;

class ServiceTokenController extends ApiController
{
    /**
     * Issue external service token.
     *
     * Issues a short-lived signed token for an external MaintOps service. The
     * caller must already be authenticated with Sanctum. The requested audience
     * decides which service can accept the token.
     *
     * @response array{
     *     success: bool,
     *     message: string,
     *     data: array{token: string, token_type: string, expires_in: int, expires_at: string, audience: string}
     * }
     */
    public function __invoke(ServiceTokenRequest $request, ServiceTokenIssuer $issuer): JsonResponse
    {
        $user = $request->user();
        $audience = $request->string('audience')->toString();

        if (! $user instanceof User || ! $user->is_active || ! $user->hasAnyRole(SystemRole::values())) {
            return $this->error('Not authorized to issue service tokens.', 403);
        }

        if ($audience === 'analytics' && ! $user->hasAnyRole([
            SystemRole::SuperAdmin->value,
            SystemRole::Admin->value,
        ])) {
            return $this->error('Not authorized to issue Analytics tokens.', 403);
        }

        return $this->success(
            data: $issuer->issueFor($user, $audience),
            message: 'Service token issued.',
        );
    }
}
