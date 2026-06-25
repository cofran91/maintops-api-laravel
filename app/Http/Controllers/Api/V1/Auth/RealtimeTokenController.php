<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Services\Realtime\RealtimeTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeTokenController extends ApiController
{
    /**
     * Issue realtime token.
     *
     * Issues a short-lived signed token for the realtime gateway. The token
     * carries only the identity context needed by the gateway to derive
     * authorized Socket.IO rooms.
     *
     * @response array{
     *     success: bool,
     *     message: string,
     *     data: array{token: string, token_type: string, expires_in: int, expires_at: string, audience: string}
     * }
     */
    public function __invoke(Request $request, RealtimeTokenIssuer $issuer): JsonResponse
    {
        return $this->success(
            data: $issuer->issueFor($request->user()),
            message: 'Realtime token issued.',
        );
    }
}
