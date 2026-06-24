<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceOrders\OrderInProgress;
use App\States\MaintenanceOrders\OrderScheduled;
use Illuminate\Validation\ValidationException;

class StartMaintenanceOrderItem extends UpdateMaintenanceOrderItemStatus
{
    public function handle(): MaintenanceOrderItem
    {
        $attributes = $this->attributes;
        $order = $this->model->maintenanceOrder;

        if (! $order->status->equals(OrderScheduled::class, OrderInProgress::class)) {
            throw ValidationException::withMessages([
                'status' => 'An item cannot be started unless its order is scheduled or in progress.',
            ]);
        }

        if ($order->status->equals(OrderScheduled::class)) {
            $order->status->transitionTo(OrderInProgress::class);
        }

        $this->scheduleLinkedVehicleTask();

        if (($attributes['started_at'] ?? $this->model->started_at) === null) {
            $attributes['started_at'] = now();
        }

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        $this->startLinkedVehicleTask();

        return $this->model;
    }

    private function scheduleLinkedVehicleTask(): void
    {
        $task = $this->model->maintenanceTask()->first();

        if ($task === null || $task->vehicle_id === null || $task->status !== MaintenanceTaskStatus::Created) {
            return;
        }

        $task->update(['status' => MaintenanceTaskStatus::Scheduled->value]);
    }

    private function startLinkedVehicleTask(): void
    {
        $task = $this->model->maintenanceTask()->first();

        if ($task === null || $task->vehicle_id === null || $task->status !== MaintenanceTaskStatus::Scheduled) {
            return;
        }

        $task->update(['status' => MaintenanceTaskStatus::Started->value]);
    }
}
