<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Services\OperationalEvents\OperationalEventRecorder;
use Spatie\ModelStates\State;

final class MaintenanceOrderObserver
{
    public function __construct(
        private readonly OperationalEventRecorder $operationalEventRecorder,
    ) {}

    public function created(MaintenanceOrder $order): void
    {
        $this->operationalEventRecorder->recordMaintenanceOrder(
            order: $order,
            eventType: 'maintenance_order.created.v1',
        );
    }

    public function updated(MaintenanceOrder $order): void
    {
        $relevantFields = [
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
        ];

        if (! $order->wasChanged($relevantFields)) {
            return;
        }

        $statusChanged = $order->wasChanged('status');
        $currentStatus = $order->status instanceof State
            ? $order->status->getValue()
            : (string) $order->status;

        $this->operationalEventRecorder->recordMaintenanceOrder(
            order: $order,
            eventType: $statusChanged
                ? sprintf('maintenance_order.%s.v1', $currentStatus)
                : 'maintenance_order.updated.v1',
            previousStatus: $statusChanged ? (string) $order->getRawOriginal('status') : null,
        );
    }
}
