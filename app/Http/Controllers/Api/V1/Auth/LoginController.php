<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends ApiController
{
    /**
     * Log in.
     *
     * Authenticates an active user by email and password. Returns a Sanctum
     * personal access token that must be sent in the Authorization header.
     *
     * @unauthenticated
     *
     * @response array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         token: string,
     *         token_type: string,
     *         user: array{id: int, name: string, email: string, roles: array<int, string>}
     *     }
     * }
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->lower()->toString())
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->success(
            data: [
                'token' => $user->createToken('api-token')->plainTextToken,
                'token_type' => 'Bearer',
                'user' => (new AuthenticatedUserResource($user))->resolve($request),
            ],
            message: 'Login successful.'
        );
    }
}
