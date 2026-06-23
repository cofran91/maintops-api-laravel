<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends ApiController
{
    /**
     * Log out.
     *
     * Revokes the Sanctum personal access token used by the current request.
     *
     * @response array{success: bool, message: string}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return $this->success(message: 'Signed out successfully.');
    }
}
