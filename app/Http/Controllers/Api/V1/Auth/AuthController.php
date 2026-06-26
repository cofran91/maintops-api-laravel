<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends ApiController
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
    public function login(LoginRequest $request): JsonResponse
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

    /**
     * Request password recovery.
     *
     * Queues a password reset email when the address belongs to an active user.
     * The public response is intentionally the same for unknown addresses.
     *
     * @unauthenticated
     *
     * @response array{success: bool, message: string}
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->lower()->toString();

        $userExists = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->exists();

        if (! $userExists) {
            return $this->success(message: 'If the email exists, a password reset link will be sent.');
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT || $status === Password::INVALID_USER) {
            return $this->success(message: 'If the email exists, a password reset link will be sent.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Reset password.
     *
     * Updates the password using the token sent by email and revokes existing
     * Sanctum personal access tokens so the user must log in again.
     *
     * @unauthenticated
     *
     * @response array{success: bool, message: string}
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->lower()->toString();

        $userExists = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->exists();

        if (! $userExists) {
            throw ValidationException::withMessages([
                'email' => [__(Password::INVALID_USER)],
            ]);
        }

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $request->string('password')->toString(),
                'password_confirmation' => $request->string('password_confirmation')->toString(),
                'token' => $request->string('token')->toString(),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(message: 'Password updated successfully.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

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
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            data: (new AuthenticatedUserResource($request->user()))->resolve($request),
            message: 'Authenticated user.'
        );
    }

    /**
     * Log out.
     *
     * Revokes the Sanctum personal access token used by the current request.
     *
     * @response array{success: bool, message: string}
     */
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return $this->success(message: 'Signed out successfully.');
    }
}
