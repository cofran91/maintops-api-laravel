<?php

namespace App\Http\Controllers\Api\V1\Owners;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Owners\OwnerRequest;
use App\Http\Resources\Api\V1\Owners\OwnerResource;
use App\ModelFilters\OwnerFilter;
use App\Models\Owner;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OwnerController extends ApiController
{
    /**
     * List owners.
     *
     * Returns registered vehicle owners managed outside the user account model.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on name, email, phone, or document number.', type: 'string', example: 'maria')]
    #[QueryParameter('is_active', description: 'Filters active or inactive owners.', type: 'boolean', example: true)]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Owner::class);

        $paginator = Owner::query()
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(OwnerFilter::perPage($request));

        return $this->success(
            data: OwnerFilter::paginatedResource($paginator, OwnerResource::class, $request),
            message: 'Owners retrieved.',
        );
    }

    /**
     * Create owner.
     *
     * Creates a vehicle owner contact. Owners are domain records and do not sign in.
     *
     * @bodyParam name string required Full owner name. Example: Maria Perez
     * @bodyParam email string required Unique contact email. Example: owner@example.com
     * @bodyParam is_active boolean required Whether the owner can be assigned to vehicles. Example: true
     * @bodyParam phone string|null Main phone number. Example: +57 300 123 4567
     * @bodyParam document_number string|null Unique document number when provided. Example: 123456789
     * @bodyParam address string|null Contact address. Example: 10 Main Street
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}
     * }, 201>
     */
    public function store(OwnerRequest $request): JsonResponse
    {
        $owner = Owner::query()->create($request->validated());

        return $this->success(
            data: (new OwnerResource($owner))->resolve($request),
            message: 'Owner created successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show owner.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}
     * }, 200>
     */
    public function show(Request $request, Owner $owner): JsonResponse
    {
        Gate::authorize('view', $owner);

        return $this->success(
            data: (new OwnerResource($owner))->resolve($request),
            message: 'Owner retrieved.',
        );
    }

    /**
     * Update owner.
     *
     * Updates an owner using the same required fields as create.
     *
     * @bodyParam name string required Full owner name. Example: Maria Perez
     * @bodyParam email string required Unique contact email. Example: owner@example.com
     * @bodyParam is_active boolean required Whether the owner can be assigned to vehicles. Example: true
     * @bodyParam phone string|null Main phone number. Example: +57 300 123 4567
     * @bodyParam document_number string|null Unique document number when provided. Example: 123456789
     * @bodyParam address string|null Contact address. Example: 10 Main Street
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}
     * }, 200>
     */
    public function update(OwnerRequest $request, Owner $owner): JsonResponse
    {
        $owner->update($request->validated());

        return $this->success(
            data: (new OwnerResource($owner->refresh()))->resolve($request),
            message: 'Owner updated successfully.',
        );
    }

    /**
     * Delete owner.
     *
     * Soft deletes an owner record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(Owner $owner): JsonResponse
    {
        Gate::authorize('delete', $owner);

        $owner->delete();

        return $this->success(message: 'Owner deleted successfully.');
    }
}
