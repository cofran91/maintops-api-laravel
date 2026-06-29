<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Api\ApiController;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsInitialSyncController extends ApiController
{
    /**
     * Internal analytics initial sync snapshot.
     *
     * Returns cursor-paginated operational snapshots for Analytics to build its
     * read model before it starts consuming Redis Streams. Contact data,
     * documents and tokens are intentionally excluded.
     *
     * @response array{
     *     success: bool,
     *     message: string,
     *     data: array{items: array<int, array<string, mixed>>, meta: array{resource: string, next_cursor: int|null}}
     * }
     */
    public function __invoke(Request $request, string $resource): JsonResponse
    {
        $limit = min(max($request->integer('limit', 100), 1), 250);
        $cursor = $request->has('cursor') ? max($request->integer('cursor'), 0) : null;

        return match ($resource) {
            'workshops' => $this->cursorPaginated(
                Workshop::query()->with('vehicleSystems:id')->orderBy('id'),
                $resource,
                $limit,
                $cursor,
                fn (Workshop $workshop): array => [
                    'id' => $workshop->id,
                    'name' => $workshop->name,
                    'code' => $workshop->code,
                    'city' => $workshop->city,
                    'weekly_schedule' => $workshop->weekly_schedule,
                    'vehicle_system_ids' => $workshop->vehicleSystems->pluck('id')->values()->all(),
                    'is_active' => (bool) $workshop->is_active,
                    'updated_at' => $workshop->updated_at?->toISOString(),
                ],
            ),
            'technicians' => $this->cursorPaginated(
                User::query()
                    ->select(['id', 'workshop_id', 'is_active', 'updated_at'])
                    ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'technician'))
                    ->orderBy('id'),
                $resource,
                $limit,
                $cursor,
                fn (User $technician): array => [
                    'id' => $technician->id,
                    'workshop_id' => $technician->workshop_id,
                    'is_active' => (bool) $technician->is_active,
                    'updated_at' => $technician->updated_at?->toISOString(),
                ],
            ),
            'maintenance-tasks' => $this->cursorPaginated(
                MaintenanceTask::query()
                    ->select([
                        'id',
                        'vehicle_id',
                        'vehicle_system_id',
                        'name',
                        'code',
                        'estimated_duration_minutes',
                        'status',
                        'is_active',
                        'updated_at',
                    ])
                    ->orderBy('id'),
                $resource,
                $limit,
                $cursor,
                fn (MaintenanceTask $task): array => [
                    'id' => $task->id,
                    'vehicle_id' => $task->vehicle_id,
                    'vehicle_system_id' => $task->vehicle_system_id,
                    'name' => $task->name,
                    'code' => $task->code,
                    'estimated_duration_minutes' => $task->estimated_duration_minutes,
                    'status' => $task->status?->getValue(),
                    'is_active' => (bool) $task->is_active,
                    'updated_at' => $task->updated_at?->toISOString(),
                ],
            ),
            'maintenance-orders' => $this->cursorPaginated(
                MaintenanceOrder::query()
                    ->select([
                        'id',
                        'vehicle_id',
                        'advisor_id',
                        'workshop_id',
                        'technician_id',
                        'status',
                        'scheduled_at',
                        'started_at',
                        'finished_at',
                        'delivered_at',
                        'cancelled_at',
                        'updated_at',
                    ])
                    ->orderBy('id'),
                $resource,
                $limit,
                $cursor,
                fn (MaintenanceOrder $order): array => [
                    'id' => $order->id,
                    'vehicle_id' => $order->vehicle_id,
                    'advisor_id' => $order->advisor_id,
                    'workshop_id' => $order->workshop_id,
                    'technician_id' => $order->technician_id,
                    'status' => $order->status?->getValue(),
                    'scheduled_at' => $order->scheduled_at?->toISOString(),
                    'started_at' => $order->started_at?->toISOString(),
                    'finished_at' => $order->finished_at?->toISOString(),
                    'delivered_at' => $order->delivered_at?->toISOString(),
                    'cancelled_at' => $order->cancelled_at?->toISOString(),
                    'updated_at' => $order->updated_at?->toISOString(),
                ],
            ),
            'maintenance-order-items' => $this->cursorPaginated(
                MaintenanceOrderItem::query()
                    ->select([
                        'id',
                        'maintenance_order_id',
                        'maintenance_task_id',
                        'maintenance_plan_id',
                        'status',
                        'odometer_km',
                        'planned_duration_minutes',
                        'pending_owner_approval_at',
                        'scheduled_at',
                        'scheduled_ends_at',
                        'started_at',
                        'finished_at',
                        'rejected_at',
                        'cancelled_at',
                        'updated_at',
                    ])
                    ->orderBy('id'),
                $resource,
                $limit,
                $cursor,
                fn (MaintenanceOrderItem $item): array => [
                    'id' => $item->id,
                    'maintenance_order_id' => $item->maintenance_order_id,
                    'maintenance_task_id' => $item->maintenance_task_id,
                    'maintenance_plan_id' => $item->maintenance_plan_id,
                    'status' => $item->status?->getValue(),
                    'odometer_km' => $item->odometer_km,
                    'planned_duration_minutes' => $item->planned_duration_minutes,
                    'pending_owner_approval_at' => $item->pending_owner_approval_at?->toISOString(),
                    'scheduled_at' => $item->scheduled_at?->toISOString(),
                    'scheduled_ends_at' => $item->scheduled_ends_at?->toISOString(),
                    'started_at' => $item->started_at?->toISOString(),
                    'finished_at' => $item->finished_at?->toISOString(),
                    'rejected_at' => $item->rejected_at?->toISOString(),
                    'cancelled_at' => $item->cancelled_at?->toISOString(),
                    'updated_at' => $item->updated_at?->toISOString(),
                ],
            ),
            default => $this->error(__('api.exceptions.unsupported_analytics_initial_sync_resource'), 404),
        };
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $transform
     */
    private function cursorPaginated(
        Builder $query,
        string $resource,
        int $limit,
        ?int $cursor,
        callable $transform,
    ): JsonResponse {
        if ($cursor !== null) {
            $query->where($query->getModel()->getQualifiedKeyName(), '>', $cursor);
        }

        $records = $query->limit($limit + 1)->get();
        $hasMore = $records->count() > $limit;
        $items = $records->take($limit)->values();
        $nextCursor = $hasMore && $items->isNotEmpty()
            ? (int) $items->last()->getKey()
            : null;

        return $this->success(
            data: [
                'items' => $items
                    ->map($transform)
                    ->values()
                    ->all(),
                'meta' => [
                    'resource' => $resource,
                    'next_cursor' => $nextCursor,
                ],
            ],
            message: __('api.messages.analytics_initial_sync.snapshot_retrieved'),
        );
    }
}
