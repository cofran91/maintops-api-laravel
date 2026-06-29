<?php

namespace App\Http\Controllers\Api\V1\VehicleSystems;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\VehicleSystems\VehicleSystemResource;
use App\ModelFilters\VehicleSystemFilter;
use App\Models\VehicleSystem;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleSystemController extends ApiController
{
    /**
     * List vehicle systems.
     *
     * Returns the seeded vehicle system catalog used by operational modules.
     *
     * Codes: `ENGINE`, `BRAKES`, `ELECTRICAL`, `COOLING`, `TRANSMISSION`, `FUEL`,
     * `HYDRAULIC`, `SUSPENSION`, `TIRES`, `BODYWORK`.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on code or name.', type: 'string', example: 'engine')]
    #[QueryParameter('code', description: 'Filters by exact code. Values: ENGINE, BRAKES, ELECTRICAL, COOLING, TRANSMISSION, FUEL, HYDRAULIC, SUSPENSION, TIRES, BODYWORK.', type: 'string', example: 'BRAKES')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        $paginator = VehicleSystem::query()
            ->orderBy('code')
            ->filter($request->query())
            ->paginateFilter(VehicleSystemFilter::perPage($request));

        return $this->success(
            data: VehicleSystemFilter::paginatedResource($paginator, VehicleSystemResource::class, $request),
            message: __('api.messages.vehicle_systems.retrieved'),
        );
    }
}
