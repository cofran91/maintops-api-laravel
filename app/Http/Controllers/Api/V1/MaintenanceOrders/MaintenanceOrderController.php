<?php

namespace App\Http\Controllers\Api\V1\MaintenanceOrders;

use App\Actions\MaintenanceOrders\UpdateMaintenanceOrderAction;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MaintenanceOrders\MaintenanceOrderRequest;
use App\Http\Resources\Api\V1\MaintenanceOrders\MaintenanceOrderResource;
use App\ModelFilters\MaintenanceOrderFilter;
use App\Models\MaintenanceOrder;
use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceOrderController extends ApiController
{
    /**
     * List maintenance orders.
     *
     * Returns orders with their vehicle, owner, advisor, workshop, technician and items. System admins see
     * every order, advisors see every order, workshop managers see their workshop orders, and technicians
     * see their assigned orders.
     */
    #[QueryParameter('search', description: 'Filters by vehicle, owner, advisor, technician, or workshop fields.', type: 'string', example: 'ABC123')]
    #[QueryParameter('vehicle_id', description: 'Filters by vehicle ID.', type: 'integer', example: 10)]
    #[QueryParameter('owner_id', description: 'Filters by owner ID.', type: 'integer', example: 25)]
    #[QueryParameter('advisor_id', description: 'Filters by advisor user ID.', type: 'integer', example: 12)]
    #[QueryParameter('workshop_id', description: 'Filters by workshop ID. Accepts null, none, or unassigned.', type: 'integer', example: 4)]
    #[QueryParameter('without_workshop', description: 'When true, returns orders without workshop assignment.', type: 'boolean', example: true)]
    #[QueryParameter('technician_id', description: 'Filters by technician user ID. Accepts null, none, or unassigned.', type: 'integer', example: 15)]
    #[QueryParameter('without_technician', description: 'When true, returns orders without technician assignment.', type: 'boolean', example: true)]
    #[QueryParameter('status', description: 'Filters by order status.', type: 'string', example: 'created')]
    #[QueryParameter('scheduled_from', description: 'Filters orders scheduled on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('scheduled_to', description: 'Filters orders scheduled on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('created_from', description: 'Filters orders created on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters orders created on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MaintenanceOrder::class);

        $query = MaintenanceOrder::query()
            ->with($this->relations())
            ->latest('id')
            ->filter($request->query());

        $this->scopeForActor($query, $request->user());

        $paginator = $query->paginateFilter(MaintenanceOrderFilter::perPage($request));

        return $this->success(
            data: MaintenanceOrderFilter::paginatedResource($paginator, MaintenanceOrderResource::class, $request),
            message: 'Maintenance orders retrieved.',
        );
    }

    /**
     * Create maintenance order.
     *
     * Creates an order in `created` status. When `advisor_id` is omitted, the authenticated user is used.
     *
     * @bodyParam vehicle_id integer required Order vehicle ID. Example: 10
     * @bodyParam advisor_id integer Advisor user ID. Advisor users are always assigned to themselves. Example: 12
     */
    public function store(MaintenanceOrderRequest $request): JsonResponse
    {
        $maintenanceOrder = MaintenanceOrder::query()
            ->create(array_merge($request->validated(), [
                'status' => MaintenanceOrderStatus::Created->value,
            ]))
            ->load($this->relations());

        return $this->success(
            data: (new MaintenanceOrderResource($maintenanceOrder))->resolve($request),
            message: 'Maintenance order created successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show maintenance order.
     */
    public function show(Request $request, MaintenanceOrder $maintenanceOrder): JsonResponse
    {
        Gate::authorize('view', $maintenanceOrder);

        return $this->success(
            data: (new MaintenanceOrderResource($maintenanceOrder->load($this->relations())))->resolve($request),
            message: 'Maintenance order retrieved.',
        );
    }

    /**
     * Update maintenance order.
     *
     * Updates the order status. Other fields are ignored by this endpoint.
     *
     * @bodyParam status string required Target order status. Values: approved, cancelled, delivered, rejected. Example: approved
     */
    public function update(
        MaintenanceOrderRequest $request,
        MaintenanceOrder $maintenanceOrder,
        UpdateMaintenanceOrderAction $updateMaintenanceOrderAction
    ): JsonResponse {
        $maintenanceOrder = $updateMaintenanceOrderAction
            ->execute($maintenanceOrder, $request->validated())
            ->load($this->relations());

        return $this->success(
            data: (new MaintenanceOrderResource($maintenanceOrder))->resolve($request),
            message: 'Maintenance order updated successfully.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'vehicle.owner',
            'advisor.roles',
            'workshop',
            'technician.roles',
            'items.maintenanceTask.vehicleSystem',
            'items.maintenancePlan',
        ];
    }

    private function scopeForActor(Builder $query, mixed $actor): void
    {
        if (! $actor instanceof User) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($actor->hasRole([
            SystemRole::SuperAdmin->value,
            SystemRole::Admin->value,
        ])) {
            return;
        }

        if ($actor->hasRole(SystemRole::WorkshopManager->value)) {
            $query->whereHas('workshop', function (Builder $query) use ($actor): void {
                $query->where('manager_user_id', $actor->getKey());
            });

            return;
        }

        if ($actor->hasRole(SystemRole::Advisor->value)) {
            return;
        }

        if ($actor->hasRole(SystemRole::Technician->value)) {
            $query->where('technician_id', $actor->getKey());

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
