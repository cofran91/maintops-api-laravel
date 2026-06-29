<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceOrders\OrderRejected;
use App\States\MaintenanceTasks\TaskCreated;
use App\States\MaintenanceTasks\TaskRejected;
use App\States\MaintenanceTasks\TaskScheduled;
use Illuminate\Validation\ValidationException;

class RejectMaintenanceOrderItem extends UpdateMaintenanceOrderItemStatus
{
    public function handle(): MaintenanceOrderItem
    {
        if ($this->model->scheduled_at !== null) {
            throw ValidationException::withMessages([
                'status' => __('api.validation.maintenance_order_items.reject_scheduled'),
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

        if ($task === null || $task->vehicle_id === null || $task->status->equals(TaskRejected::class)) {
            return;
        }

        if (! $task->status->equals(TaskCreated::class, TaskScheduled::class)) {
            return;
        }

        $task->status->transitionTo(TaskRejected::class);
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
