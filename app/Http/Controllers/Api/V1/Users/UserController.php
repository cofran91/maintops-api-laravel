<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Users\UserRequest;
use App\Http\Resources\Api\V1\Users\UserResource;
use App\ModelFilters\UserFilter;
use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserController extends ApiController
{
    /**
     * List users.
     *
     * Returns users visible to the authenticated user according to the role hierarchy.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             name: string,
     *             email: string,
     *             roles: array<int, string>,
     *             is_active: bool,
     *             phone: string|null,
     *             document_number: string|null,
     *             address: string|null,
     *             email_verified_at: string|null,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on name, email, phone, or document number.', type: 'string', example: 'maria')]
    #[QueryParameter('role', description: 'Filters by a visible role. Values: super_admin, admin, workshop_manager, advisor, technician.', type: 'string', example: 'technician')]
    #[QueryParameter('is_active', description: 'Filters active or inactive users.', type: 'boolean', example: true)]
    #[QueryParameter('without_workshop', description: 'When true, returns users that are not assigned as a workshop manager.', type: 'boolean', example: true)]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $paginator = User::query()
            ->with('roles')
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(UserFilter::perPage($request));

        return $this->success(
            data: UserFilter::paginatedResource($paginator, UserResource::class, $request),
            message: 'Users retrieved.',
        );
    }

    /**
     * Create user.
     *
     * Creates a user and assigns exactly one system role. Role assignment is limited by hierarchy.
     *
     * @bodyParam name string required Full user name. Example: Maria Perez
     * @bodyParam email string required Unique email used for sign-in. Example: technician@example.com
     * @bodyParam password string required Password with at least 8 characters. Example: password
     * @bodyParam password_confirmation string required Exact password confirmation. Example: password
     * @bodyParam role string required Role to assign. Example: technician
     * @bodyParam is_active boolean required Whether the user can sign in. Example: true
     * @bodyParam phone string|null Main phone number. Example: +57 300 123 4567
     * @bodyParam document_number string|null Unique document number when provided. Example: 123456789
     * @bodyParam address string|null Contact address. Example: 10 Main Street
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, name: string, email: string, roles: array<int, string>}}, 201>
     */
    public function store(UserRequest $request, CreateUserAction $createUserAction): JsonResponse
    {
        $user = $createUserAction
            ->execute($request->validated())
            ->load('roles');

        return $this->success(
            data: (new UserResource($user))->resolve($request),
            message: 'User created successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show user.
     *
     * Returns a user when the authenticated user is allowed to view that role.
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, name: string, email: string, roles: array<int, string>}}, 200>
     */
    public function show(Request $request, User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return $this->success(
            data: (new UserResource($user->load('roles')))->resolve($request),
            message: 'User retrieved.',
        );
    }

    /**
     * Update user.
     *
     * Updates profile fields and replaces the user's role. The update request uses the same required fields as create.
     *
     * @bodyParam name string required Full user name. Example: Maria Perez
     * @bodyParam email string required Unique email used for sign-in. Example: technician@example.com
     * @bodyParam password string required Password with at least 8 characters. Example: new-password
     * @bodyParam password_confirmation string required Exact password confirmation. Example: new-password
     * @bodyParam role string required Role to assign. Example: technician
     * @bodyParam is_active boolean required Whether the user can sign in. Example: true
     * @bodyParam phone string|null Main phone number. Example: +57 300 123 4567
     * @bodyParam document_number string|null Unique document number when provided. Example: 123456789
     * @bodyParam address string|null Contact address. Example: 10 Main Street
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, name: string, email: string, roles: array<int, string>}}, 200>
     */
    public function update(UserRequest $request, User $user, UpdateUserAction $updateUserAction): JsonResponse
    {
        $user = $updateUserAction
            ->execute($user, $request->validated())
            ->load('roles');

        return $this->success(
            data: (new UserResource($user))->resolve($request),
            message: 'User updated successfully.',
        );
    }

    /**
     * Delete user.
     *
     * Soft deletes a user below the authenticated user's hierarchy. Users cannot delete themselves.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return $this->success(message: 'User deleted successfully.');
    }
}
