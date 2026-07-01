<?php

namespace App\Http\Controllers\Api\V1\MaintenanceTasks;

use App\Enums\MaintenanceTaskStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MaintenanceTasks\MaintenanceTaskRequest;
use App\Http\Resources\Api\V1\MaintenanceTasks\MaintenanceTaskResource;
use App\ModelFilters\MaintenanceTaskFilter;
use App\Models\MaintenanceTask;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceTaskController extends ApiController
{
    /**
     * List maintenance tasks.
     *
     * Returns reusable or vehicle-specific tasks with their vehicle system.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             vehicle_id: int|null,
     *             vehicle: array{id: int, owner_id: int, license_plate: string, brand: string|null, model: string|null, year: int|null, color: string|null, odometer_km: int, created_at: string|null, updated_at: string|null}|null,
     *             vehicle_system_id: int,
     *             vehicle_system: array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null},
     *             name: string,
     *             code: string,
     *             description: string|null,
     *             estimated_duration_minutes: int,
     *             status: string,
     *             is_active: bool,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by task, vehicle, or vehicle system fields.', type: 'string', example: 'oil')]
    #[QueryParameter('name', description: 'Filters by partial task name.', type: 'string', example: 'Oil change')]
    #[QueryParameter('code', description: 'Filters by partial internal code. The value is normalized to uppercase.', type: 'string', example: 'OIL')]
    #[QueryParameter('vehicle_id', description: 'Filters by vehicle ID. Accepts null, none, or unassigned for reusable tasks.', type: 'integer', example: 10)]
    #[QueryParameter('without_vehicle', description: 'When true, returns reusable tasks with no vehicle assignment.', type: 'boolean', example: true)]
    #[QueryParameter('vehicle_system_id', description: 'Filters by vehicle system ID.', type: 'integer', example: 1)]
    #[QueryParameter('status', description: 'Filters by operational status. Values: created, scheduled, started, cancelled, completed, rejected.', type: 'string', example: 'created')]
    #[QueryParameter('is_active', description: 'Filters active or inactive tasks.', type: 'boolean', example: true)]
    #[QueryParameter('estimated_duration_from', description: 'Filters tasks with estimated duration greater than or equal to these minutes.', type: 'integer', example: 30)]
    #[QueryParameter('estimated_duration_to', description: 'Filters tasks with estimated duration less than or equal to these minutes.', type: 'integer', example: 180)]
    #[QueryParameter('created_from', description: 'Filters tasks created on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters tasks created on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MaintenanceTask::class);

        $paginator = MaintenanceTask::query()
            ->with(['vehicle', 'vehicleSystem'])
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(MaintenanceTaskFilter::perPage($request));

        return $this->success(
            data: MaintenanceTaskFilter::paginatedResource($paginator, MaintenanceTaskResource::class, $request),
            message: __('api.messages.maintenance_tasks.retrieved'),
        );
    }

    /**
     * Create maintenance task.
     *
     * Creates a reusable task for a vehicle system or a vehicle-specific task.
     *
     * @bodyParam vehicle_id integer|null Specific vehicle ID. Advisors must provide it. Example: 10
     * @bodyParam vehicle_system_id integer required Vehicle system ID. Example: 1
     * @bodyParam name string required Visible task name. Example: Oil change
     * @bodyParam code string required Unique task code. Stored as uppercase slug. Example: OIL-CHANGE
     * @bodyParam description string|null Operational task description. Example: Drain oil, replace filter, and register odometer.
     * @bodyParam estimated_duration_minutes integer required Estimated duration in minutes. Minimum 1 and maximum 10080. Example: 90
     * @bodyParam is_active boolean required Whether the task can be selected in operations. Example: true
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, vehicle_id: int|null, vehicle: array{id: int, owner_id: int, license_plate: string, brand: string|null, model: string|null, year: int|null, color: string|null, odometer_km: int, created_at: string|null, updated_at: string|null}|null, vehicle_system_id: int, vehicle_system: array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}, name: string, code: string, description: string|null, estimated_duration_minutes: int, status: string, is_active: bool, created_at: string|null, updated_at: string|null}}, 201>
     */
    public function store(MaintenanceTaskRequest $request): JsonResponse
    {
        $maintenanceTask = MaintenanceTask::query()
            ->create(array_merge($request->validated(), [
                'status' => MaintenanceTaskStatus::Created->value,
            ]))
            ->load(['vehicle', 'vehicleSystem']);

        return $this->success(
            data: (new MaintenanceTaskResource($maintenanceTask))->resolve($request),
            message: __('api.messages.maintenance_tasks.created'),
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show maintenance task.
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, vehicle_id: int|null, vehicle: array{id: int, owner_id: int, license_plate: string, brand: string|null, model: string|null, year: int|null, color: string|null, odometer_km: int, created_at: string|null, updated_at: string|null}|null, vehicle_system_id: int, vehicle_system: array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}, name: string, code: string, description: string|null, estimated_duration_minutes: int, status: string, is_active: bool, created_at: string|null, updated_at: string|null}}, 200>
     */
    public function show(Request $request, MaintenanceTask $maintenanceTask): JsonResponse
    {
        Gate::authorize('view', $maintenanceTask);

        return $this->success(
            data: (new MaintenanceTaskResource($maintenanceTask->load(['vehicle', 'vehicleSystem'])))->resolve($request),
            message: __('api.messages.maintenance_tasks.retrieved_one'),
        );
    }

    /**
     * Update maintenance task.
     *
     * Updates task catalog fields using the same required fields as create.
     *
     * @bodyParam vehicle_id integer|null Specific vehicle ID. Send null to make the task reusable. Example: 10
     * @bodyParam vehicle_system_id integer required Vehicle system ID. Example: 1
     * @bodyParam name string required Visible task name. Example: Oil and filter change
     * @bodyParam code string required Unique task code. Stored as uppercase slug. Example: OIL-FILTER-CHANGE
     * @bodyParam description string|null Operational task description. Example: Replace oil, filter, and register odometer.
     * @bodyParam estimated_duration_minutes integer required Estimated duration in minutes. Minimum 1 and maximum 10080. Example: 75
     * @bodyParam is_active boolean required Whether the task can be selected in operations. Example: true
     *
     * @return JsonResponse<array{success: bool, message: string, data: array{id: int, vehicle_id: int|null, vehicle: array{id: int, owner_id: int, license_plate: string, brand: string|null, model: string|null, year: int|null, color: string|null, odometer_km: int, created_at: string|null, updated_at: string|null}|null, vehicle_system_id: int, vehicle_system: array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}, name: string, code: string, description: string|null, estimated_duration_minutes: int, status: string, is_active: bool, created_at: string|null, updated_at: string|null}}, 200>
     */
    public function update(MaintenanceTaskRequest $request, MaintenanceTask $maintenanceTask): JsonResponse
    {
        $maintenanceTask->update($request->validated());

        return $this->success(
            data: (new MaintenanceTaskResource($maintenanceTask->refresh()->load(['vehicle', 'vehicleSystem'])))->resolve($request),
            message: __('api.messages.maintenance_tasks.updated'),
        );
    }

    /**
     * Delete maintenance task.
     *
     * Soft deletes a task catalog record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(MaintenanceTask $maintenanceTask): JsonResponse
    {
        Gate::authorize('delete', $maintenanceTask);

        $maintenanceTask->delete();

        return $this->success(message: __('api.messages.maintenance_tasks.deleted'));
    }
}
