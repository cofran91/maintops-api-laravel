<?php

namespace App\Observers;

use App\Models\MaintenanceOrderItem;
use App\Services\OperationalEvents\OperationalEventRecorder;
use Spatie\ModelStates\State;

final class MaintenanceOrderItemObserver
{
    public function __construct(
        private readonly OperationalEventRecorder $operationalEventRecorder,
    ) {}

    public function created(MaintenanceOrderItem $item): void
    {
        $this->operationalEventRecorder->recordMaintenanceOrderItem(
            item: $item,
            eventType: 'maintenance_order_item.created.v1',
        );
    }

    public function updated(MaintenanceOrderItem $item): void
    {
        $relevantFields = [
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
        ];

        if (! $item->wasChanged($relevantFields)) {
            return;
        }

        $statusChanged = $item->wasChanged('status');
        $currentStatus = $item->status instanceof State
            ? $item->status->getValue()
            : (string) $item->status;

        $this->operationalEventRecorder->recordMaintenanceOrderItem(
            item: $item,
            eventType: $statusChanged
                ? sprintf('maintenance_order_item.%s.v1', $currentStatus)
                : 'maintenance_order_item.updated.v1',
            previousStatus: $statusChanged ? (string) $item->getRawOriginal('status') : null,
        );
    }
}
