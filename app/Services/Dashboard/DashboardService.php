<?php

namespace App\Services\Dashboard;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    private const DAILY_SCHEDULE_STATUSES = [
        'scheduled',
        'in_progress',
        'completed',
    ];

    private const OPEN_ORDER_STATUSES = [
        'created',
        'pending_owner_approval',
        'approved',
        'partially_approved',
        'scheduled',
        'in_progress',
        'completed',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forActor(User $actor): array
    {
        $orders = MaintenanceOrder::query();
        $items = MaintenanceOrderItem::query();

        $this->scopeOrdersForActor($orders, $actor);
        $this->scopeItemsForActor($items, $actor);

        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $tomorrowStart = $now->copy()->addDay()->startOfDay();

        $ordersByStatus = $this->ordersByStatus(clone $orders);
        $awaitingScheduling = (clone $orders)
            ->whereIn('status', [
                MaintenanceOrderStatus::Approved->value,
                MaintenanceOrderStatus::PartiallyApproved->value,
            ])
            ->count();
        $overdueActivities = (clone $items)
            ->where('status', MaintenanceOrderItemStatus::Scheduled->value)
            ->where('scheduled_at', '<', $now)
            ->count();

        return [
            'orders_by_status' => $ordersByStatus,
            'metrics' => [
                'total_orders' => array_sum($ordersByStatus),
                'open_orders' => (clone $orders)
                    ->whereIn('status', self::OPEN_ORDER_STATUSES)
                    ->count(),
                'awaiting_owner_approval' => $ordersByStatus[MaintenanceOrderStatus::PendingOwnerApproval->value],
                'awaiting_scheduling' => $awaitingScheduling,
                'active_orders' => $ordersByStatus[MaintenanceOrderStatus::InProgress->value],
                'completed_today' => (clone $orders)
                    ->where('status', MaintenanceOrderStatus::Completed->value)
                    ->whereBetween('finished_at', [$todayStart, $tomorrowStart])
                    ->count(),
                'overdue_activities' => $overdueActivities,
            ],
            'activities' => [
                'pending' => (clone $items)
                    ->whereIn('status', [
                        MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                        MaintenanceOrderItemStatus::Scheduled->value,
                    ])
                    ->count(),
                'active' => (clone $items)
                    ->where('status', MaintenanceOrderItemStatus::InProgress->value)
                    ->count(),
            ],
            'today_schedules' => $this->scheduledOrders(
                query: (clone $orders)->whereIn('status', self::DAILY_SCHEDULE_STATUSES),
                from: $todayStart,
                before: $tomorrowStart,
            ),
            'upcoming_schedules' => $this->scheduledOrders(
                query: (clone $orders)->where('status', MaintenanceOrderStatus::Scheduled->value),
                from: $tomorrowStart,
            ),
            'role_context' => $this->roleContext($actor, clone $orders, clone $items, $todayStart, $tomorrowStart),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roleContext(
        User $actor,
        Builder $orders,
        Builder $items,
        CarbonInterface $todayStart,
        CarbonInterface $tomorrowStart,
    ): array {
        if ($actor->hasRole([
            SystemRole::SuperAdmin->value,
            SystemRole::Admin->value,
        ])) {
            return [
                'role' => $actor->hasRole(SystemRole::SuperAdmin->value)
                    ? SystemRole::SuperAdmin->value
                    : SystemRole::Admin->value,
                'type' => 'system_admin',
                'orders_by_workshop' => $this->ordersByWorkshop(clone $orders),
                'technician_workload_today' => $this->technicianWorkloadToday(clone $items, $todayStart, $tomorrowStart),
                'awaiting_scheduling_orders' => $this->orderCards(
                    (clone $orders)->whereIn('status', [
                        MaintenanceOrderStatus::Approved->value,
                        MaintenanceOrderStatus::PartiallyApproved->value,
                    ]),
                ),
            ];
        }

        if ($actor->hasRole(SystemRole::Advisor->value)) {
            return [
                'role' => SystemRole::Advisor->value,
                'type' => 'advisor',
                'awaiting_owner_approval_orders' => $this->orderCards(
                    (clone $orders)->where('status', MaintenanceOrderStatus::PendingOwnerApproval->value),
                ),
                'partially_approved_orders' => $this->orderCards(
                    (clone $orders)->where('status', MaintenanceOrderStatus::PartiallyApproved->value),
                ),
                'rejected_orders' => $this->orderCards(
                    (clone $orders)->where('status', MaintenanceOrderStatus::Rejected->value),
                ),
                'upcoming_deliveries' => $this->orderCards(
                    (clone $orders)
                        ->where('status', MaintenanceOrderStatus::Completed->value)
                        ->whereNotNull('finished_at')
                        ->oldest('finished_at'),
                ),
            ];
        }

        if ($actor->hasRole(SystemRole::WorkshopManager->value)) {
            return [
                'role' => SystemRole::WorkshopManager->value,
                'type' => 'workshop_manager',
                'technician_workload_today' => $this->technicianWorkloadToday(clone $items, $todayStart, $tomorrowStart),
                'technicians_without_assignments_today' => $this->techniciansWithoutAssignmentsToday(
                    actor: $actor,
                    items: clone $items,
                    todayStart: $todayStart,
                    tomorrowStart: $tomorrowStart,
                ),
                'active_items' => $this->itemCards(
                    (clone $items)->where('status', MaintenanceOrderItemStatus::InProgress->value),
                ),
            ];
        }

        if ($actor->hasRole(SystemRole::Technician->value)) {
            return [
                'role' => SystemRole::Technician->value,
                'type' => 'technician',
                'current_item' => $this->firstItemCard(
                    (clone $items)->where('status', MaintenanceOrderItemStatus::InProgress->value),
                ),
                'next_item' => $this->firstItemCard(
                    (clone $items)
                        ->where('status', MaintenanceOrderItemStatus::Scheduled->value)
                        ->where('scheduled_at', '>=', now())
                        ->oldest('scheduled_at'),
                ),
                'today_queue' => $this->itemCards(
                    (clone $items)
                        ->whereIn('status', [
                            MaintenanceOrderItemStatus::Scheduled->value,
                            MaintenanceOrderItemStatus::InProgress->value,
                        ])
                        ->where('scheduled_at', '>=', $todayStart)
                        ->where('scheduled_at', '<', $tomorrowStart)
                        ->oldest('scheduled_at'),
                ),
                'completed_today_count' => (clone $items)
                    ->where('status', MaintenanceOrderItemStatus::Completed->value)
                    ->whereBetween('finished_at', [$todayStart, $tomorrowStart])
                    ->count(),
            ];
        }

        return [
            'role' => null,
            'type' => 'none',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function ordersByStatus(Builder $query): array
    {
        $counts = array_fill_keys(MaintenanceOrderStatus::values(), 0);

        foreach ($query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $count) {
            $counts[$status] = (int) $count;
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduledOrders(
        Builder $query,
        CarbonInterface $from,
        ?CarbonInterface $before = null,
    ): array {
        $query
            ->with(['vehicle', 'workshop', 'technician'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $from);

        if ($before !== null) {
            $query->where('scheduled_at', '<', $before);
        }

        return $query
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn (MaintenanceOrder $order): array => [
                'maintenance_order_id' => $order->id,
                'status' => $order->status->getValue(),
                'scheduled_at' => $order->scheduled_at?->toISOString(),
                'vehicle' => $order->vehicle === null ? null : [
                    'id' => $order->vehicle->id,
                    'license_plate' => $order->vehicle->license_plate,
                    'brand' => $order->vehicle->brand,
                    'model' => $order->vehicle->model,
                ],
                'workshop' => $order->workshop === null ? null : [
                    'id' => $order->workshop->id,
                    'name' => $order->workshop->name,
                    'code' => $order->workshop->code,
                    'city' => $order->workshop->city,
                ],
                'technician' => $order->technician === null ? null : [
                    'id' => $order->technician->id,
                    'name' => $order->technician->name,
                    'email' => $order->technician->email,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ordersByWorkshop(Builder $query): array
    {
        return $query
            ->leftJoin('workshops', 'workshops.id', '=', 'maintenance_orders.workshop_id')
            ->whereIn('maintenance_orders.status', self::OPEN_ORDER_STATUSES)
            ->selectRaw('
                maintenance_orders.workshop_id,
                workshops.name as workshop_name,
                workshops.code as workshop_code,
                workshops.city as workshop_city,
                count(*) as open_orders_count
            ')
            ->groupBy(
                'maintenance_orders.workshop_id',
                'workshops.name',
                'workshops.code',
                'workshops.city',
            )
            ->orderByDesc('open_orders_count')
            ->limit(10)
            ->get()
            ->map(fn (MaintenanceOrder $order): array => [
                'workshop_id' => $order->workshop_id,
                'workshop' => $order->workshop_id === null ? null : [
                    'id' => $order->workshop_id,
                    'name' => $order->workshop_name,
                    'code' => $order->workshop_code,
                    'city' => $order->workshop_city,
                ],
                'open_orders_count' => (int) $order->open_orders_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function technicianWorkloadToday(
        Builder $query,
        CarbonInterface $todayStart,
        CarbonInterface $tomorrowStart,
    ): array {
        return $query
            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_order_items.maintenance_order_id')
            ->join('users as technicians', 'technicians.id', '=', 'maintenance_orders.technician_id')
            ->whereIn('maintenance_order_items.status', [
                MaintenanceOrderItemStatus::Scheduled->value,
                MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->where('maintenance_order_items.scheduled_at', '>=', $todayStart)
            ->where('maintenance_order_items.scheduled_at', '<', $tomorrowStart)
            ->selectRaw('
                maintenance_orders.technician_id,
                technicians.name as technician_name,
                technicians.email as technician_email,
                count(*) as assigned_items_count,
                coalesce(sum(maintenance_order_items.planned_duration_minutes), 0) as planned_minutes
            ')
            ->groupBy(
                'maintenance_orders.technician_id',
                'technicians.name',
                'technicians.email',
            )
            ->orderByDesc('planned_minutes')
            ->limit(10)
            ->get()
            ->map(fn (MaintenanceOrderItem $item): array => [
                'technician_id' => $item->technician_id,
                'technician' => [
                    'id' => $item->technician_id,
                    'name' => $item->technician_name,
                    'email' => $item->technician_email,
                ],
                'assigned_items_count' => (int) $item->assigned_items_count,
                'planned_minutes' => (int) $item->planned_minutes,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderCards(Builder $query, int $limit = 10): array
    {
        return $query
            ->with(['vehicle.owner', 'advisor', 'workshop', 'technician'])
            ->latest('maintenance_orders.id')
            ->limit($limit)
            ->get()
            ->map(fn (MaintenanceOrder $order): array => $this->orderCard($order))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderCard(MaintenanceOrder $order): array
    {
        return [
            'maintenance_order_id' => $order->id,
            'status' => $order->status->getValue(),
            'scheduled_at' => $order->scheduled_at?->toISOString(),
            'finished_at' => $order->finished_at?->toISOString(),
            'vehicle' => $order->vehicle === null ? null : [
                'id' => $order->vehicle->id,
                'license_plate' => $order->vehicle->license_plate,
                'brand' => $order->vehicle->brand,
                'model' => $order->vehicle->model,
                'owner' => $order->vehicle->owner === null ? null : [
                    'id' => $order->vehicle->owner->id,
                    'name' => $order->vehicle->owner->name,
                    'email' => $order->vehicle->owner->email,
                ],
            ],
            'advisor' => $order->advisor === null ? null : [
                'id' => $order->advisor->id,
                'name' => $order->advisor->name,
                'email' => $order->advisor->email,
            ],
            'workshop' => $order->workshop === null ? null : [
                'id' => $order->workshop->id,
                'name' => $order->workshop->name,
                'code' => $order->workshop->code,
                'city' => $order->workshop->city,
            ],
            'technician' => $order->technician === null ? null : [
                'id' => $order->technician->id,
                'name' => $order->technician->name,
                'email' => $order->technician->email,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemCards(Builder $query, int $limit = 10): array
    {
        return $query
            ->with([
                'maintenanceOrder.vehicle',
                'maintenanceOrder.workshop',
                'maintenanceTask.vehicleSystem',
            ])
            ->limit($limit)
            ->get()
            ->map(fn (MaintenanceOrderItem $item): array => $this->itemCard($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstItemCard(Builder $query): ?array
    {
        $item = $query
            ->with([
                'maintenanceOrder.vehicle',
                'maintenanceOrder.workshop',
                'maintenanceTask.vehicleSystem',
            ])
            ->first();

        return $item instanceof MaintenanceOrderItem
            ? $this->itemCard($item)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemCard(MaintenanceOrderItem $item): array
    {
        return [
            'maintenance_order_item_id' => $item->id,
            'maintenance_order_id' => $item->maintenance_order_id,
            'status' => $item->status->getValue(),
            'scheduled_at' => $item->scheduled_at?->toISOString(),
            'scheduled_ends_at' => $item->scheduled_ends_at?->toISOString(),
            'planned_duration_minutes' => $item->planned_duration_minutes,
            'task' => $item->maintenanceTask === null ? null : [
                'id' => $item->maintenanceTask->id,
                'name' => $item->maintenanceTask->name,
                'code' => $item->maintenanceTask->code,
                'vehicle_system' => $item->maintenanceTask->vehicleSystem === null ? null : [
                    'id' => $item->maintenanceTask->vehicleSystem->id,
                    'code' => $item->maintenanceTask->vehicleSystem->code,
                    'name' => $item->maintenanceTask->vehicleSystem->name,
                ],
            ],
            'vehicle' => $item->maintenanceOrder?->vehicle === null ? null : [
                'id' => $item->maintenanceOrder->vehicle->id,
                'license_plate' => $item->maintenanceOrder->vehicle->license_plate,
                'brand' => $item->maintenanceOrder->vehicle->brand,
                'model' => $item->maintenanceOrder->vehicle->model,
            ],
            'workshop' => $item->maintenanceOrder?->workshop === null ? null : [
                'id' => $item->maintenanceOrder->workshop->id,
                'name' => $item->maintenanceOrder->workshop->name,
                'code' => $item->maintenanceOrder->workshop->code,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function techniciansWithoutAssignmentsToday(
        User $actor,
        Builder $items,
        CarbonInterface $todayStart,
        CarbonInterface $tomorrowStart,
    ): array {
        $workshopIds = Workshop::query()
            ->where('manager_user_id', $actor->getKey())
            ->pluck('id');

        if ($workshopIds->isEmpty()) {
            return [];
        }

        $assignedTechnicianIds = $items
            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_order_items.maintenance_order_id')
            ->whereIn('maintenance_order_items.status', [
                MaintenanceOrderItemStatus::Scheduled->value,
                MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->where('maintenance_order_items.scheduled_at', '>=', $todayStart)
            ->where('maintenance_order_items.scheduled_at', '<', $tomorrowStart)
            ->whereNotNull('maintenance_orders.technician_id')
            ->pluck('maintenance_orders.technician_id');

        return User::query()
            ->role(SystemRole::Technician->value)
            ->where('is_active', true)
            ->whereIn('workshop_id', $workshopIds)
            ->whereNotIn('id', $assignedTechnicianIds)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'workshop_id'])
            ->map(fn (User $technician): array => [
                'id' => $technician->id,
                'name' => $technician->name,
                'email' => $technician->email,
                'workshop_id' => $technician->workshop_id,
            ])
            ->values()
            ->all();
    }

    private function scopeOrdersForActor(Builder $query, User $actor): void
    {
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

    private function scopeItemsForActor(Builder $query, User $actor): void
    {
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
