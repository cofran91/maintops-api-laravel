<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceOrders\OrderCompleted;
use App\States\MaintenanceOrders\OrderInProgress;
use Illuminate\Validation\ValidationException;

class CompleteMaintenanceOrderItem extends UpdateMaintenanceOrderItemStatus
{
    public function handle(): MaintenanceOrderItem
    {
        $attributes = $this->attributes;

        if (($attributes['started_at'] ?? $this->model->started_at) === null) {
            throw ValidationException::withMessages([
                'status' => 'An item cannot be completed before it has started.',
            ]);
        }

        if (($attributes['finished_at'] ?? $this->model->finished_at) === null) {
            $attributes['finished_at'] = now();
        }

        if (($attributes['odometer_km'] ?? $this->model->odometer_km) === null) {
            $order = $this->model->maintenanceOrder()->with('vehicle')->firstOrFail();
            $attributes['odometer_km'] = $order->vehicle?->odometer_km;
        }

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        $this->finishLinkedVehicleTask();

        $order = $this->model->maintenanceOrder()->firstOrFail();
        $hasOpenItems = $order->items()
            ->whereIn('status', [
                MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                MaintenanceOrderItemStatus::Scheduled->value,
                MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->exists();

        if (! $hasOpenItems && $order->status->equals(OrderInProgress::class)) {
            $order->status->transitionTo(OrderCompleted::class);
        }

        return $this->model;
    }

    private function finishLinkedVehicleTask(): void
    {
        $task = $this->model->maintenanceTask()->first();

        if ($task === null || $task->vehicle_id === null || $task->status === MaintenanceTaskStatus::Completed) {
            return;
        }

        if (! in_array($task->status, [
            MaintenanceTaskStatus::Scheduled,
            MaintenanceTaskStatus::Started,
        ], true)) {
            return;
        }

        $task->update(['status' => MaintenanceTaskStatus::Completed->value]);
    }
}
