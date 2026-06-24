<?php

namespace App\Http\Controllers\Api\V1\MaintenanceOrders;

use App\Actions\MaintenanceOrders\UpdateMaintenanceOrderItemAction;
use App\Enums\SystemRole;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MaintenanceOrders\MaintenanceOrderItemRequest;
use App\Http\Resources\Api\V1\MaintenanceOrders\MaintenanceOrderItemResource;
use App\ModelFilters\MaintenanceOrderItemFilter;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaintenanceOrderItemController extends ApiController
{
    /**
     * List maintenance order items.
     *
     * Item visibility inherits the parent order scope.
     */
    #[QueryParameter('search', description: 'Filters by task, plan, or order vehicle plate.', type: 'string', example: 'oil')]
    #[QueryParameter('maintenance_order_id', description: 'Filters by maintenance order ID.', type: 'integer', example: 10)]
    #[QueryParameter('maintenance_task_id', description: 'Filters by maintenance task ID.', type: 'integer', example: 15)]
    #[QueryParameter('maintenance_plan_id', description: 'Filters by maintenance plan ID. Accepts null, none, or unassigned.', type: 'integer', example: 3)]
    #[QueryParameter('without_plan', description: 'When true, returns items without a plan.', type: 'boolean', example: true)]
    #[QueryParameter('status', description: 'Filters by item status.', type: 'string', example: 'pending_owner_approval')]
    #[QueryParameter('pending_owner_approval_from', description: 'Filters items sent for approval on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('pending_owner_approval_to', description: 'Filters items sent for approval on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('scheduled_from', description: 'Filters items scheduled on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('scheduled_to', description: 'Filters items scheduled on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MaintenanceOrderItem::class);

        $query = MaintenanceOrderItem::query()
            ->with($this->relations())
            ->latest('id')
            ->filter($request->query());

        $this->scopeForActor($query, $request->user());

        $paginator = $query->paginateFilter(MaintenanceOrderItemFilter::perPage($request));

        return $this->success(
            data: MaintenanceOrderItemFilter::paginatedResource($paginator, MaintenanceOrderItemResource::class, $request),
            message: 'Maintenance order items retrieved.',
        );
    }

    /**
     * Show maintenance order item.
     */
    public function show(Request $request, MaintenanceOrderItem $maintenanceOrderItem): JsonResponse
    {
        Gate::authorize('view', $maintenanceOrderItem);

        return $this->success(
            data: (new MaintenanceOrderItemResource($maintenanceOrderItem->load($this->relations())))->resolve($request),
            message: 'Maintenance order item retrieved.',
        );
    }

    /**
     * Update maintenance order item.
     *
     * Updates the item status through the configured state machine.
     *
     * @bodyParam status string required Target item status. Values: in_progress, completed, rejected, cancelled. Example: in_progress
     */
    public function update(
        MaintenanceOrderItemRequest $request,
        MaintenanceOrderItem $maintenanceOrderItem,
        UpdateMaintenanceOrderItemAction $updateMaintenanceOrderItemAction
    ): JsonResponse {
        $maintenanceOrderItem = $updateMaintenanceOrderItemAction
            ->execute($maintenanceOrderItem, $request->validated())
            ->load($this->relations());

        return $this->success(
            data: (new MaintenanceOrderItemResource($maintenanceOrderItem))->resolve($request),
            message: 'Maintenance order item updated successfully.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'maintenanceOrder.vehicle.owner',
            'maintenanceTask.vehicleSystem',
            'maintenancePlan',
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
            $query->whereHas('maintenanceOrder.workshop', function (Builder $query) use ($actor): void {
                $query->where('manager_user_id', $actor->getKey());
            });

            return;
        }

        if ($actor->hasRole(SystemRole::Advisor->value)) {
            return;
        }

        if ($actor->hasRole(SystemRole::Technician->value)) {
            $query->whereHas('maintenanceOrder', function (Builder $query) use ($actor): void {
                $query->where('technician_id', $actor->getKey());
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
