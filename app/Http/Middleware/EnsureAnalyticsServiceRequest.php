<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnalyticsServiceRequest
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $expectedKey = config('operations.analytics.service_key');
        $providedKey = $request->header('X-Operations-Service-Key');

        if (
            ! is_string($expectedKey)
            || $expectedKey === ''
            || ! is_string($providedKey)
            || ! hash_equals($expectedKey, $providedKey)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized service request.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
