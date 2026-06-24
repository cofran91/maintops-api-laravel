<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceOrders\OrderRejected;
use Illuminate\Validation\ValidationException;

class RejectMaintenanceOrderItem extends UpdateMaintenanceOrderItemStatus
{
    public function handle(): MaintenanceOrderItem
    {
        if ($this->model->scheduled_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'A scheduled item cannot be rejected.',
            ]);
        }

        $attributes = $this->attributes;

        if (($attributes['rejected_at'] ?? $this->model->rejected_at) === null) {
            $attributes['rejected_at'] = now();
        }

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        $this->rejectLinkedVehicleTask();
        $this->rejectOrderWhenAllItemsAreRejected();

        return $this->model;
    }

    private function rejectLinkedVehicleTask(): void
    {
        $task = $this->model->maintenanceTask()->first();

        if ($task === null || $task->vehicle_id === null || $task->status === MaintenanceTaskStatus::Rejected) {
            return;
        }

        if (! in_array($task->status, [
            MaintenanceTaskStatus::Created,
            MaintenanceTaskStatus::Scheduled,
        ], true)) {
            return;
        }

        $task->update(['status' => MaintenanceTaskStatus::Rejected->value]);
    }

    private function rejectOrderWhenAllItemsAreRejected(): void
    {
        $order = $this->model->maintenanceOrder()->first();

        if ($order === null || $order->status->equals(OrderRejected::class)) {
            return;
        }

        $hasNonRejectedItems = $order->items()
            ->where('status', '!=', MaintenanceOrderItemStatus::Rejected->value)
            ->exists();

        if ($hasNonRejectedItems) {
            return;
        }

        $order->status->transitionTo(OrderRejected::class);
    }
}
