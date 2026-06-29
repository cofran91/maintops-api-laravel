<?php

namespace App\Http\Controllers\Api\V1\MaintenancePlans;

use App\Actions\MaintenancePlans\CreateMaintenancePlanAction;
use App\Actions\MaintenancePlans\UpdateMaintenancePlanAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MaintenancePlans\MaintenancePlanRequest;
use App\Http\Resources\Api\V1\MaintenancePlans\MaintenancePlanResource;
use App\ModelFilters\MaintenancePlanFilter;
use App\Models\MaintenancePlan;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MaintenancePlanController extends ApiController
{
    /**
     * List maintenance plans.
     *
     * Returns reusable maintenance plans with their active reusable tasks.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             code: string,
     *             name: string,
     *             description: string|null,
     *             recommended_interval_days: int|null,
     *             recommended_interval_km: int|null,
     *             task_ids: array<int, int>,
     *             tasks: array<int, array{id: int, vehicle_id: int|null, vehicle_system_id: int, name: string, code: string, estimated_duration_minutes: int, status: string, is_active: bool}>,
     *             is_active: bool,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by plan fields or related task fields.', type: 'string', example: 'preventive')]
    #[QueryParameter('name', description: 'Filters by partial plan name.', type: 'string', example: 'Preventive')]
    #[QueryParameter('code', description: 'Filters by partial internal code. The value is normalized to uppercase.', type: 'string', example: 'PREV')]
    #[QueryParameter('is_active', description: 'Filters active or inactive plans.', type: 'boolean', example: true)]
    #[QueryParameter('task_id', description: 'Filters plans that contain a task ID.', type: 'integer', example: 10)]
    #[QueryParameter('recommended_interval_days_from', description: 'Filters plans with a day interval greater than or equal to the value.', type: 'integer', example: 30)]
    #[QueryParameter('recommended_interval_days_to', description: 'Filters plans with a day interval less than or equal to the value.', type: 'integer', example: 180)]
    #[QueryParameter('recommended_interval_km_from', description: 'Filters plans with a kilometer interval greater than or equal to the value.', type: 'integer', example: 5000)]
    #[QueryParameter('recommended_interval_km_to', description: 'Filters plans with a kilometer interval less than or equal to the value.', type: 'integer', example: 20000)]
    #[QueryParameter('created_from', description: 'Filters plans created on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters plans created on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MaintenancePlan::class);

        $paginator = MaintenancePlan::query()
            ->with(['tasks.vehicle', 'tasks.vehicleSystem'])
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(MaintenancePlanFilter::perPage($request));

        return $this->success(
            data: MaintenancePlanFilter::paginatedResource($paginator, MaintenancePlanResource::class, $request),
            message: __('api.messages.maintenance_plans.retrieved'),
        );
    }

    /**
     * Create maintenance plan.
     *
     * Creates a reusable plan and syncs its active reusable task list.
     *
     * @bodyParam code string required Unique plan code. Stored as uppercase slug. Example: PREVENTIVE-10K
     * @bodyParam name string required Visible plan name. Example: Preventive 10k
     * @bodyParam description string|null Operational plan description. Example: Recommended activities every 10000 km.
     * @bodyParam recommended_interval_days integer|null Recommended interval in days. Minimum 1 and maximum 3650. Example: 180
     * @bodyParam recommended_interval_km integer|null Recommended interval in kilometers. Minimum 1 and maximum 1000000. Example: 10000
     * @bodyParam task_ids integer[] required Active reusable task IDs to include. Example: [1,2,3]
     * @bodyParam is_active boolean required Whether the plan can be selected in operations. Example: true
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, code: string, name: string, recommended_interval_days: int|null, recommended_interval_km: int|null, task_ids: array<int, int>, is_active: bool}}, 201>
     */
    public function store(MaintenancePlanRequest $request, CreateMaintenancePlanAction $createMaintenancePlanAction): JsonResponse
    {
        $maintenancePlan = $createMaintenancePlanAction
            ->execute($request->validated())
            ->load(['tasks.vehicle', 'tasks.vehicleSystem']);

        return $this->success(
            data: (new MaintenancePlanResource($maintenancePlan))->resolve($request),
            message: __('api.messages.maintenance_plans.created'),
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show maintenance plan.
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, code: string, name: string, recommended_interval_days: int|null, recommended_interval_km: int|null, task_ids: array<int, int>, is_active: bool}}, 200>
     */
    public function show(Request $request, MaintenancePlan $maintenancePlan): JsonResponse
    {
        Gate::authorize('view', $maintenancePlan);

        return $this->success(
            data: (new MaintenancePlanResource($maintenancePlan->load(['tasks.vehicle', 'tasks.vehicleSystem'])))->resolve($request),
            message: __('api.messages.maintenance_plans.retrieved_one'),
        );
    }

    /**
     * Update maintenance plan.
     *
     * Updates plan catalog fields and replaces the task list using the same required fields as create.
     *
     * @bodyParam code string required Unique plan code. Stored as uppercase slug. Example: PREVENTIVE-10K
     * @bodyParam name string required Visible plan name. Example: Updated preventive 10k
     * @bodyParam description string|null Operational plan description. Example: Activities recommended every 10000 km or 180 days.
     * @bodyParam recommended_interval_days integer|null Recommended interval in days. Minimum 1 and maximum 3650. Example: 180
     * @bodyParam recommended_interval_km integer|null Recommended interval in kilometers. Minimum 1 and maximum 1000000. Example: 10000
     * @bodyParam task_ids integer[] required Active reusable task IDs to include. Example: [1,2]
     * @bodyParam is_active boolean required Whether the plan can be selected in operations. Example: true
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, code: string, name: string, recommended_interval_days: int|null, recommended_interval_km: int|null, task_ids: array<int, int>, is_active: bool}}, 200>
     */
    public function update(
        MaintenancePlanRequest $request,
        MaintenancePlan $maintenancePlan,
        UpdateMaintenancePlanAction $updateMaintenancePlanAction
    ): JsonResponse {
        $maintenancePlan = $updateMaintenancePlanAction
            ->execute($maintenancePlan, $request->validated())
            ->load(['tasks.vehicle', 'tasks.vehicleSystem']);

        return $this->success(
            data: (new MaintenancePlanResource($maintenancePlan))->resolve($request),
            message: __('api.messages.maintenance_plans.updated'),
        );
    }

    /**
     * Delete maintenance plan.
     *
     * Soft deletes a plan record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(MaintenancePlan $maintenancePlan): JsonResponse
    {
        Gate::authorize('delete', $maintenancePlan);

        $maintenancePlan->delete();

        return $this->success(message: __('api.messages.maintenance_plans.deleted'));
    }
}
