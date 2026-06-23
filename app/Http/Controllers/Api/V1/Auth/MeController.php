<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends ApiController
{
    /**
     * Authenticated user.
     *
     * Returns the basic profile and roles assigned to the authenticated user.
     *
     * @response array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, roles: array<int, string>}
     * }
     */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->success(
            data: (new AuthenticatedUserResource($request->user()))->resolve($request),
            message: 'Authenticated user.'
        );
    }
}
