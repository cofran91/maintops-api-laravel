<?php

namespace App\Console\Commands;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use App\Models\Workshop;
use App\States\MaintenanceOrderItems\OrderItemRejected;
use App\States\MaintenanceOrderItems\OrderItemScheduled;
use App\States\MaintenanceOrders\OrderScheduled;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleApprovedMaintenanceOrdersCommand extends Command
{
    protected $signature = 'maintenance-orders:schedule-approved {--days=7 : Number of days to search for availability.}';

    protected $description = 'Schedule approved maintenance orders by assigning a workshop, technician, and item dates.';

    public function handle(): int
    {
        $maxDays = max(1, (int) $this->option('days'));

        MaintenanceOrder::query()
            ->whereIn('status', [
                MaintenanceOrderStatus::Approved->value,
                MaintenanceOrderStatus::PartiallyApproved->value,
            ])
            ->orderBy('id')
            ->chunkById(50, function ($orders) use ($maxDays): void {
                foreach ($orders as $order) {
                    $this->processOrder($order, $maxDays);
                }
            });

        return self::SUCCESS;
    }

    private function processOrder(MaintenanceOrder $order, int $maxDays): void
    {
        DB::transaction(function () use ($order, $maxDays): void {
            $order = MaintenanceOrder::query()
                ->with(['items.maintenanceTask'])
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $items = $order->items
                ->filter(fn (MaintenanceOrderItem $item): bool => $item->status->getValue() === MaintenanceOrderItemStatus::PendingOwnerApproval->value)
                ->sortBy('id')
                ->values();

            if ($items->isEmpty()) {
                return;
            }

            $selections = $this->workshopSelections($items);

            if ($selections === []) {
                $this->rejectItems($items);

                return;
            }

            $plan = $this->bestSchedulePlan($selections, $maxDays);

            if ($plan === null) {
                return;
            }

            $itemStarts = $plan['item_starts'];

            $items->each(function (MaintenanceOrderItem $item) use ($itemStarts): void {
                if (! array_key_exists($item->id, $itemStarts)) {
                    return;
                }

                $scheduledAt = $itemStarts[$item->id];
                $plannedDuration = $item->maintenanceTask?->estimated_duration_minutes;

                $item->status->transitionTo(OrderItemScheduled::class, [
                    'scheduled_at' => $scheduledAt,
                    'planned_duration_minutes' => $plannedDuration,
                    'scheduled_ends_at' => $plannedDuration === null
                        ? null
                        : $scheduledAt->copy()->addMinutes($plannedDuration),
                ]);
            });

            $this->rejectItems($items->filter(
                fn (MaintenanceOrderItem $item): bool => ! array_key_exists($item->id, $itemStarts),
            ));

            $order->status->transitionTo(OrderScheduled::class, [
                'workshop_id' => $plan['workshop']->id,
                'technician_id' => $plan['technician']->id,
                'scheduled_at' => $plan['first_start'],
            ]);
        });
    }

    /**
     * @param  EloquentCollection<int, MaintenanceOrderItem>  $items
     * @return array<int, array{workshop: Workshop, items: EloquentCollection<int, MaintenanceOrderItem>}>
     */
    private function workshopSelections(EloquentCollection $items): array
    {
        $systemIds = $items
            ->map(fn (MaintenanceOrderItem $item): ?int => $item->maintenanceTask?->vehicle_system_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selections = [];

        foreach ($this->eligibleWorkshops($systemIds) as $workshop) {
            $supportedItems = $this->itemsSupportedBy($workshop, $items);

            if ($supportedItems->isEmpty()) {
                continue;
            }

            $selections[] = [
                'workshop' => $workshop,
                'items' => $supportedItems,
            ];
        }

        usort($selections, function (array $left, array $right): int {
            return $right['items']->count() <=> $left['items']->count()
                ?: $left['workshop']->id <=> $right['workshop']->id;
        });

        return $selections;
    }

    /**
     * @param  array<int, int>  $systemIds
     * @return EloquentCollection<int, Workshop>
     */
    private function eligibleWorkshops(array $systemIds): EloquentCollection
    {
        return Workshop::query()
            ->where('is_active', true)
            ->with('vehicleSystems:id')
            ->whereHas('vehicleSystems', function (Builder $query) use ($systemIds): void {
                $query->whereIn('vehicle_systems.id', $systemIds);
            })
            ->whereHas('technicians', function (Builder $query): void {
                $query
                    ->where('is_active', true)
                    ->whereHas('roles', function (Builder $query): void {
                        $query->where('name', SystemRole::Technician->value);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, MaintenanceOrderItem>  $items
     * @return EloquentCollection<int, MaintenanceOrderItem>
     */
    private function itemsSupportedBy(Workshop $workshop, EloquentCollection $items): EloquentCollection
    {
        $systemIds = $workshop->vehicleSystems->modelKeys();

        return $items
            ->filter(fn (MaintenanceOrderItem $item): bool => in_array(
                $item->maintenanceTask?->vehicle_system_id,
                $systemIds,
                true,
            ))
            ->values();
    }

    /**
     * @param  array<int, array{workshop: Workshop, items: EloquentCollection<int, MaintenanceOrderItem>}>  $selections
     * @return array{workshop: Workshop, technician: User, item_starts: array<int, Carbon>, first_start: Carbon, scheduled_items_count: int}|null
     */
    private function bestSchedulePlan(array $selections, int $maxDays): ?array
    {
        $startDate = $this->roundedNow()->startOfDay();

        for ($dayOffset = 0; $dayOffset < $maxDays; $dayOffset++) {
            $date = $startDate->copy()->addDays($dayOffset);
            $bestPlan = null;
            $remainingDays = $maxDays - $dayOffset;

            foreach ($selections as $selection) {
                $plan = $this->bestTechnicianPlanForDate(
                    $selection['items'],
                    $selection['workshop'],
                    $date,
                    $remainingDays,
                );

                if ($plan === null) {
                    continue;
                }

                if ($bestPlan === null || $this->planIsBetter($plan, $bestPlan)) {
                    $bestPlan = $plan;
                }
            }

            if ($bestPlan !== null) {
                return $bestPlan;
            }
        }

        return null;
    }

    /**
     * @param  EloquentCollection<int, MaintenanceOrderItem>  $items
     * @return array{workshop: Workshop, technician: User, item_starts: array<int, Carbon>, first_start: Carbon, scheduled_items_count: int}|null
     */
    private function bestTechnicianPlanForDate(
        EloquentCollection $items,
        Workshop $workshop,
        Carbon $date,
        int $daysRemaining,
    ): ?array {
        $bestPlan = null;

        foreach ($this->activeTechniciansFor($workshop) as $technician) {
            $plan = $this->schedulePlanForDate($items, $workshop, $technician, $date, $daysRemaining);

            if ($plan === null) {
                continue;
            }

            if (
                $bestPlan === null
                || $plan['first_start']->lt($bestPlan['first_start'])
                || (
                    $plan['first_start']->equalTo($bestPlan['first_start'])
                    && $plan['technician']->id < $bestPlan['technician']->id
                )
            ) {
                $bestPlan = $plan;
            }
        }

        return $bestPlan;
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function activeTechniciansFor(Workshop $workshop): EloquentCollection
    {
        return $workshop->technicians()
            ->where('is_active', true)
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', SystemRole::Technician->value);
            })
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, MaintenanceOrderItem>  $items
     * @return array{workshop: Workshop, technician: User, item_starts: array<int, Carbon>, first_start: Carbon, scheduled_items_count: int}|null
     */
    private function schedulePlanForDate(
        EloquentCollection $items,
        Workshop $workshop,
        User $technician,
        Carbon $date,
        int $daysRemaining,
    ): ?array {
        $remainingItems = $items->values()->all();
        $itemStarts = [];
        $firstStart = null;

        for ($dayOffset = 0; $dayOffset < $daysRemaining && $remainingItems !== []; $dayOffset++) {
            $currentDate = $date->copy()->addDays($dayOffset);
            $availability = $this->availabilityWindowForDate($workshop, $technician, $currentDate);

            if ($availability === null) {
                if ($dayOffset === 0) {
                    return null;
                }

                continue;
            }

            [$startsAt, $closesAt] = $availability;
            $segment = $this->buildDaySegment($remainingItems, $startsAt, $closesAt);

            if ($segment === []) {
                if ($dayOffset === 0) {
                    return null;
                }

                continue;
            }

            foreach ($segment as $entry) {
                $itemStarts[$entry['item']->id] = $entry['start']->copy();
                $firstStart ??= $entry['start']->copy();
            }

            $remainingItems = array_slice($remainingItems, count($segment));
        }

        if ($remainingItems !== [] || $firstStart === null) {
            return null;
        }

        return [
            'workshop' => $workshop,
            'technician' => $technician,
            'item_starts' => $itemStarts,
            'first_start' => $firstStart,
            'scheduled_items_count' => count($itemStarts),
        ];
    }

    /**
     * @param  array{workshop: Workshop, technician: User, item_starts: array<int, Carbon>, first_start: Carbon, scheduled_items_count: int}  $candidate
     * @param  array{workshop: Workshop, technician: User, item_starts: array<int, Carbon>, first_start: Carbon, scheduled_items_count: int}  $current
     */
    private function planIsBetter(array $candidate, array $current): bool
    {
        return $candidate['scheduled_items_count'] > $current['scheduled_items_count']
            || (
                $candidate['scheduled_items_count'] === $current['scheduled_items_count']
                && $candidate['first_start']->lt($current['first_start'])
            )
            || (
                $candidate['scheduled_items_count'] === $current['scheduled_items_count']
                && $candidate['first_start']->equalTo($current['first_start'])
                && $candidate['workshop']->id < $current['workshop']->id
            );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function availabilityWindowForDate(Workshop $workshop, User $technician, Carbon $date): ?array
    {
        $window = $this->workdayWindow($workshop, $date);

        if ($window === null) {
            return null;
        }

        [$opensAt, $closesAt] = $window;
        $startsAt = $opensAt->copy();
        $now = $this->roundedNow();

        if ($date->isSameDay($now) && $startsAt->lt($now)) {
            $startsAt = $now;
        }

        $lastScheduledEnd = $this->lastScheduledActivityEndOnDate($technician, $date);

        if ($lastScheduledEnd !== null && $lastScheduledEnd->gt($startsAt)) {
            $startsAt = $lastScheduledEnd->copy();
        }

        if ($startsAt->gte($closesAt)) {
            return null;
        }

        return [$startsAt, $closesAt];
    }

    /**
     * @param  array<int, MaintenanceOrderItem>  $items
     * @return array<int, array{item: MaintenanceOrderItem, start: Carbon, end: Carbon}>
     */
    private function buildDaySegment(array $items, Carbon $startsAt, Carbon $closesAt): array
    {
        $segment = [];
        $cursor = $startsAt->copy();

        foreach ($items as $item) {
            $duration = $item->maintenanceTask?->estimated_duration_minutes;

            if ($duration === null) {
                break;
            }

            $endsAt = $cursor->copy()->addMinutes($duration);

            if ($endsAt->gt($closesAt)) {
                break;
            }

            $segment[] = [
                'item' => $item,
                'start' => $cursor->copy(),
                'end' => $endsAt->copy(),
            ];
            $cursor = $endsAt;
        }

        return $segment;
    }

    private function lastScheduledActivityEndOnDate(User $technician, Carbon $date): ?Carbon
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $ends = collect();

        MaintenanceOrder::query()
            ->with('items.maintenanceTask')
            ->where('status', MaintenanceOrderStatus::Scheduled->value)
            ->where('technician_id', $technician->id)
            ->where(function (Builder $query) use ($dayStart, $dayEnd): void {
                $query
                    ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
                    ->orWhereHas('items', function (Builder $query) use ($dayStart, $dayEnd): void {
                        $query->whereBetween('scheduled_at', [$dayStart, $dayEnd]);
                    });
            })
            ->get()
            ->each(function (MaintenanceOrder $order) use ($dayStart, $dayEnd, $ends): void {
                if ($order->scheduled_at?->betweenIncluded($dayStart, $dayEnd)) {
                    $ends->push($order->scheduled_at->copy());
                }

                $order->items
                    ->filter(fn (MaintenanceOrderItem $item): bool => $item->scheduled_at?->betweenIncluded($dayStart, $dayEnd) ?? false)
                    ->each(function (MaintenanceOrderItem $item) use ($ends): void {
                        $ends->push(
                            $item->scheduled_ends_at?->copy()
                                ?? $item->scheduled_at->copy()->addMinutes(
                                    $item->planned_duration_minutes
                                        ?? $item->maintenanceTask?->estimated_duration_minutes
                                        ?? 0,
                                ),
                        );
                    });
            });

        return $ends->sort()->last();
    }

    /**
     * @param  EloquentCollection<int, MaintenanceOrderItem>  $items
     */
    private function rejectItems(EloquentCollection $items): void
    {
        $items->each(function (MaintenanceOrderItem $item): void {
            if ($item->status->equals(OrderItemRejected::class)) {
                return;
            }

            $item->status->transitionTo(OrderItemRejected::class);
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function workdayWindow(Workshop $workshop, Carbon $date): ?array
    {
        $schedule = $workshop->weekly_schedule ?? [];
        $day = strtolower($date->englishDayOfWeek);

        if (! isset($schedule[$day])) {
            return null;
        }

        return [
            $this->timeOnDate($date, (string) $schedule[$day]['opens_at']),
            $this->timeOnDate($date, (string) $schedule[$day]['closes_at']),
        ];
    }

    private function timeOnDate(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $date->copy()->setTime($hour, $minute);
    }

    private function roundedNow(): Carbon
    {
        $now = now();

        if ($now->second > 0 || $now->microsecond > 0) {
            $now = $now->copy()->addMinute();
        }

        return $now->copy()->second(0)->microsecond(0);
    }
}
