<?php

namespace App\Http\Controllers\Api\V1\Vehicles;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Vehicles\VehicleRequest;
use App\Http\Resources\Api\V1\Vehicles\VehicleResource;
use App\ModelFilters\VehicleFilter;
use App\Models\Vehicle;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends ApiController
{
    /**
     * List vehicles.
     *
     * Returns vehicles ordered from newest to oldest with their owner contact.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             owner_id: int,
     *             owner: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null},
     *             license_plate: string,
     *             brand: string|null,
     *             model: string|null,
     *             year: int|null,
     *             color: string|null,
     *             odometer_km: int,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on plate, brand, model, color, or owner fields.', type: 'string', example: 'ABC')]
    #[QueryParameter('license_plate', description: 'Filters by partial match on license plate. The value is normalized to uppercase.', type: 'string', example: 'ABC123')]
    #[QueryParameter('brand', description: 'Filters by vehicle brand.', type: 'string', example: 'Toyota')]
    #[QueryParameter('model', description: 'Filters by vehicle model or line.', type: 'string', example: 'Hilux')]
    #[QueryParameter('year', description: 'Filters by vehicle year.', type: 'integer', example: 2024)]
    #[QueryParameter('color', description: 'Filters by vehicle color.', type: 'string', example: 'White')]
    #[QueryParameter('owner_id', description: 'Filters by owner ID.', type: 'integer', example: 25)]
    #[QueryParameter('created_from', description: 'Filters vehicles created on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters vehicles created on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $paginator = Vehicle::query()
            ->with('owner')
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(VehicleFilter::perPage($request));

        return $this->success(
            data: VehicleFilter::paginatedResource($paginator, VehicleResource::class, $request),
            message: 'Vehicles retrieved.',
        );
    }

    /**
     * Create vehicle.
     *
     * Creates a vehicle attached to an active owner.
     *
     * @bodyParam owner_id integer required Active owner ID. Example: 25
     * @bodyParam license_plate string required Unique license plate. Stored uppercase. Example: ABC123
     * @bodyParam brand string|null Vehicle brand. Example: Toyota
     * @bodyParam model string|null Vehicle model or line. Example: Hilux
     * @bodyParam year integer|null Vehicle year. Minimum 1900 and maximum next year. Example: 2024
     * @bodyParam color string|null Main vehicle color. Example: White
     * @bodyParam odometer_km integer required Current odometer in kilometers. Example: 15200
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         owner_id: int,
     *         owner: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null},
     *         license_plate: string,
     *         brand: string|null,
     *         model: string|null,
     *         year: int|null,
     *         color: string|null,
     *         odometer_km: int,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 201>
     */
    public function store(VehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::query()
            ->create($request->validated())
            ->load('owner');

        return $this->success(
            data: (new VehicleResource($vehicle))->resolve($request),
            message: 'Vehicle created successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show vehicle.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         owner_id: int,
     *         owner: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null},
     *         license_plate: string,
     *         brand: string|null,
     *         model: string|null,
     *         year: int|null,
     *         color: string|null,
     *         odometer_km: int,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 200>
     */
    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('view', $vehicle);

        return $this->success(
            data: (new VehicleResource($vehicle->load('owner')))->resolve($request),
            message: 'Vehicle retrieved.',
        );
    }

    /**
     * Update vehicle.
     *
     * Updates vehicle data using the same required fields as create.
     *
     * @bodyParam owner_id integer required Active owner ID. Example: 25
     * @bodyParam license_plate string required Unique license plate. Stored uppercase. Example: ABC123
     * @bodyParam brand string|null Vehicle brand. Example: Toyota
     * @bodyParam model string|null Vehicle model or line. Example: Hilux
     * @bodyParam year integer|null Vehicle year. Minimum 1900 and maximum next year. Example: 2024
     * @bodyParam color string|null Main vehicle color. Example: White
     * @bodyParam odometer_km integer required Current odometer in kilometers. Example: 18000
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         owner_id: int,
     *         owner: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null},
     *         license_plate: string,
     *         brand: string|null,
     *         model: string|null,
     *         year: int|null,
     *         color: string|null,
     *         odometer_km: int,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 200>
     */
    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($request->validated());

        return $this->success(
            data: (new VehicleResource($vehicle->refresh()->load('owner')))->resolve($request),
            message: 'Vehicle updated successfully.',
        );
    }

    /**
     * Delete vehicle.
     *
     * Soft deletes a vehicle record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('delete', $vehicle);

        $vehicle->delete();

        return $this->success(message: 'Vehicle deleted successfully.');
    }
}
