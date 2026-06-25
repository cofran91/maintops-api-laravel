<?php

namespace App\Services\OperationalEvents;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\OperationalEventOutbox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\ModelStates\State;

final class OperationalEventRecorder
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public function recordMaintenanceOrder(
        MaintenanceOrder $order,
        string $eventType,
        ?string $previousStatus = null,
        array $overrides = [],
    ): OperationalEventOutbox {
        $status = $overrides['status'] ?? $order->status;
        $status = $status instanceof State ? $status->getValue() : (string) $status;

        return $this->record(
            eventType: $eventType,
            aggregateType: 'maintenance_order',
            aggregateId: (int) $order->getKey(),
            payload: [
                'aggregate' => [
                    'type' => 'maintenance_order',
                    'id' => (int) $order->getKey(),
                ],
                'data' => [
                    'id' => (int) $order->getKey(),
                    'maintenance_order_id' => (int) $order->getKey(),
                    'vehicle_id' => $overrides['vehicle_id'] ?? $order->vehicle_id,
                    'advisor_id' => $overrides['advisor_id'] ?? $order->advisor_id,
                    'workshop_id' => $overrides['workshop_id'] ?? $order->workshop_id,
                    'technician_id' => $overrides['technician_id'] ?? $order->technician_id,
                    'status' => $status,
                    'previous_status' => $previousStatus,
                    'scheduled_at' => $this->isoDate($overrides['scheduled_at'] ?? $order->scheduled_at),
                    'started_at' => $this->isoDate($overrides['started_at'] ?? $order->started_at),
                    'finished_at' => $this->isoDate($overrides['finished_at'] ?? $order->finished_at),
                    'delivered_at' => $this->isoDate($overrides['delivered_at'] ?? $order->delivered_at),
                    'cancelled_at' => $this->isoDate($overrides['cancelled_at'] ?? $order->cancelled_at),
                ],
            ],
            targets: $this->targetsForOrder($order, $overrides),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function recordMaintenanceOrderItem(
        MaintenanceOrderItem $item,
        string $eventType,
        ?string $previousStatus = null,
        array $overrides = [],
    ): OperationalEventOutbox {
        $order = $item->maintenanceOrder()->firstOrFail();
        $status = $overrides['status'] ?? $item->status;
        $status = $status instanceof State ? $status->getValue() : (string) $status;

        return $this->record(
            eventType: $eventType,
            aggregateType: 'maintenance_order_item',
            aggregateId: (int) $item->getKey(),
            payload: [
                'aggregate' => [
                    'type' => 'maintenance_order_item',
                    'id' => (int) $item->getKey(),
                ],
                'data' => [
                    'id' => (int) $item->getKey(),
                    'maintenance_order_item_id' => (int) $item->getKey(),
                    'maintenance_order_id' => $item->maintenance_order_id,
                    'maintenance_task_id' => $item->maintenance_task_id,
                    'maintenance_plan_id' => $item->maintenance_plan_id,
                    'status' => $status,
                    'previous_status' => $previousStatus,
                    'odometer_km' => $overrides['odometer_km'] ?? $item->odometer_km,
                    'planned_duration_minutes' => $overrides['planned_duration_minutes'] ?? $item->planned_duration_minutes,
                    'pending_owner_approval_at' => $this->isoDate(
                        $overrides['pending_owner_approval_at'] ?? $item->pending_owner_approval_at,
                    ),
                    'scheduled_at' => $this->isoDate($overrides['scheduled_at'] ?? $item->scheduled_at),
                    'scheduled_ends_at' => $this->isoDate(
                        $overrides['scheduled_ends_at'] ?? $item->scheduled_ends_at,
                    ),
                    'started_at' => $this->isoDate($overrides['started_at'] ?? $item->started_at),
                    'finished_at' => $this->isoDate($overrides['finished_at'] ?? $item->finished_at),
                    'rejected_at' => $this->isoDate($overrides['rejected_at'] ?? $item->rejected_at),
                    'cancelled_at' => $this->isoDate($overrides['cancelled_at'] ?? $item->cancelled_at),
                ],
            ],
            targets: $this->targetsForOrder($order, $overrides),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $targets
     */
    private function record(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        array $payload,
        array $targets,
    ): OperationalEventOutbox {
        return OperationalEventOutbox::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'actor_id' => Auth::id(),
            'payload' => $payload,
            'targets' => $targets,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function targetsForOrder(MaintenanceOrder $order, array $overrides = []): array
    {
        return [
            'workshop_id' => $overrides['workshop_id'] ?? $order->workshop_id,
            'workshop_manager_id' => $overrides['workshop_manager_id'] ?? $order->workshop?->manager_user_id,
            'technician_id' => $overrides['technician_id'] ?? $order->technician_id,
            'advisor_id' => $overrides['advisor_id'] ?? $order->advisor_id,
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return method_exists($value, 'toISOString') ? $value->toISOString() : (string) $value;
    }
}
