<?php

namespace App\Http\Controllers\Api\V1\Workshops;

use App\Actions\Workshops\CreateWorkshopAction;
use App\Actions\Workshops\UpdateWorkshopAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Workshops\WorkshopRequest;
use App\Http\Resources\Api\V1\Workshops\WorkshopResource;
use App\ModelFilters\WorkshopFilter;
use App\Models\Workshop;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class WorkshopController extends ApiController
{
    /**
     * List workshops.
     *
     * Returns workshops with their assigned manager and served vehicle systems.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             manager_user_id: int,
     *             manager: array{id: int, name: string, email: string, roles: array<int, string>},
     *             name: string,
     *             code: string,
     *             address: string|null,
     *             city: string|null,
     *             phone: string|null,
     *             email: string|null,
     *             weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *             vehicle_system_ids: array<int, int>,
     *             vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *             technician_user_ids: array<int, int>,
     *             technicians: array<int, array{id: int, name: string, email: string, roles: array<int, string>, workshop_id: int|null}>,
     *             is_active: bool,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on name, code, city, email, phone, or manager fields.', type: 'string', example: 'north')]
    #[QueryParameter('name', description: 'Filters by partial match on workshop name.', type: 'string', example: 'North Workshop')]
    #[QueryParameter('code', description: 'Filters by partial match on internal workshop code.', type: 'string', example: 'NORTH')]
    #[QueryParameter('city', description: 'Filters by city.', type: 'string', example: 'Bogota')]
    #[QueryParameter('email', description: 'Filters by workshop email.', type: 'string', example: 'north@maint.test')]
    #[QueryParameter('phone', description: 'Filters by workshop phone.', type: 'string', example: '+57 300')]
    #[QueryParameter('is_active', description: 'Filters active or inactive workshops.', type: 'boolean', example: true)]
    #[QueryParameter('manager_user_id', description: 'Filters by assigned manager user ID.', type: 'integer', example: 12)]
    #[QueryParameter('vehicle_system_id', description: 'Filters workshops that serve a vehicle system ID.', type: 'integer', example: 1)]
    #[QueryParameter('created_from', description: 'Filters workshops created on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters workshops created on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Workshop::class);

        $paginator = Workshop::query()
            ->with(['manager.roles', 'vehicleSystems', 'technicians.roles'])
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(WorkshopFilter::perPage($request));

        return $this->success(
            data: WorkshopFilter::paginatedResource($paginator, WorkshopResource::class, $request),
            message: 'Workshops retrieved.',
        );
    }

    /**
     * Create workshop.
     *
     * Creates a workshop and synchronizes the vehicle systems it serves.
     *
     * @bodyParam manager_user_id integer required Active user ID with the workshop_manager role. Example: 2
     * @bodyParam name string required Workshop commercial name. Example: North Workshop
     * @bodyParam code string required Unique workshop code. Stored uppercase and slugged. Example: NORTH-WORKSHOP
     * @bodyParam address string|null Workshop physical address. Example: 10 Main Street
     * @bodyParam city string|null City where the workshop operates. Example: Bogota
     * @bodyParam phone string|null Main workshop phone. Example: +57 300 123 4567
     * @bodyParam email string|null Operational workshop email. Example: north@maint.test
     * @bodyParam weekly_schedule object required Weekly schedule by day. Example: {"monday":{"opens_at":"08:00","closes_at":"17:00"}}
     * @bodyParam vehicle_system_ids integer[] required Vehicle system IDs served by the workshop. Example: [1,2,3]
     * @bodyParam technician_user_ids integer[] required Active technician user IDs assigned to the workshop. Send an empty array when none. Example: [15,16]
     * @bodyParam is_active boolean required Whether the workshop is available for operations. Example: true
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         manager_user_id: int,
     *         manager: array{id: int, name: string, email: string, roles: array<int, string>},
     *         name: string,
     *         code: string,
     *         address: string|null,
     *         city: string|null,
     *         phone: string|null,
     *         email: string|null,
     *         weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *         vehicle_system_ids: array<int, int>,
     *         vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         technician_user_ids: array<int, int>,
     *         technicians: array<int, array{id: int, name: string, email: string, roles: array<int, string>, workshop_id: int|null}>,
     *         is_active: bool,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 201>
     */
    public function store(WorkshopRequest $request, CreateWorkshopAction $createWorkshopAction): JsonResponse
    {
        $workshop = $createWorkshopAction
            ->execute($request->validated())
            ->load(['manager.roles', 'vehicleSystems', 'technicians.roles']);

        return $this->success(
            data: (new WorkshopResource($workshop))->resolve($request),
            message: 'Workshop created successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show workshop.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         manager_user_id: int,
     *         manager: array{id: int, name: string, email: string, roles: array<int, string>},
     *         name: string,
     *         code: string,
     *         address: string|null,
     *         city: string|null,
     *         phone: string|null,
     *         email: string|null,
     *         weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *         vehicle_system_ids: array<int, int>,
     *         vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         technician_user_ids: array<int, int>,
     *         technicians: array<int, array{id: int, name: string, email: string, roles: array<int, string>, workshop_id: int|null}>,
     *         is_active: bool,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 200>
     */
    public function show(Request $request, Workshop $workshop): JsonResponse
    {
        Gate::authorize('view', $workshop);

        return $this->success(
            data: (new WorkshopResource($workshop->load(['manager.roles', 'vehicleSystems', 'technicians.roles'])))->resolve($request),
            message: 'Workshop retrieved.',
        );
    }

    /**
     * Update workshop.
     *
     * Updates workshop data and replaces the served vehicle systems using the same required fields as create.
     *
     * @bodyParam manager_user_id integer required Active user ID with the workshop_manager role. Example: 2
     * @bodyParam name string required Workshop commercial name. Example: North Workshop
     * @bodyParam code string required Unique workshop code. Stored uppercase and slugged. Example: NORTH-WORKSHOP
     * @bodyParam address string|null Workshop physical address. Example: 10 Main Street
     * @bodyParam city string|null City where the workshop operates. Example: Bogota
     * @bodyParam phone string|null Main workshop phone. Example: +57 300 123 4567
     * @bodyParam email string|null Operational workshop email. Example: north@maint.test
     * @bodyParam weekly_schedule object required Weekly schedule by day. Example: {"monday":{"opens_at":"08:00","closes_at":"17:00"}}
     * @bodyParam vehicle_system_ids integer[] required Vehicle system IDs served by the workshop. Example: [1,2,3]
     * @bodyParam technician_user_ids integer[] required Replaces the active technician user IDs assigned to the workshop. Send an empty array when none. Example: [15,16]
     * @bodyParam is_active boolean required Whether the workshop is available for operations. Example: true
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         manager_user_id: int,
     *         manager: array{id: int, name: string, email: string, roles: array<int, string>},
     *         name: string,
     *         code: string,
     *         address: string|null,
     *         city: string|null,
     *         phone: string|null,
     *         email: string|null,
     *         weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *         vehicle_system_ids: array<int, int>,
     *         vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         technician_user_ids: array<int, int>,
     *         technicians: array<int, array{id: int, name: string, email: string, roles: array<int, string>, workshop_id: int|null}>,
     *         is_active: bool,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 200>
     */
    public function update(
        WorkshopRequest $request,
        Workshop $workshop,
        UpdateWorkshopAction $updateWorkshopAction
    ): JsonResponse {
        $workshop = $updateWorkshopAction
            ->execute($workshop, $request->validated())
            ->load(['manager.roles', 'vehicleSystems', 'technicians.roles']);

        return $this->success(
            data: (new WorkshopResource($workshop))->resolve($request),
            message: 'Workshop updated successfully.',
        );
    }

    /**
     * Delete workshop.
     *
     * Soft deletes a workshop record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(Workshop $workshop): JsonResponse
    {
        Gate::authorize('delete', $workshop);

        $workshop->delete();

        return $this->success(message: 'Workshop deleted successfully.');
    }
}
