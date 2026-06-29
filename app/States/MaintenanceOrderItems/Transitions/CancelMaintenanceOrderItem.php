<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceTasks\TaskCancelled;
use App\States\MaintenanceTasks\TaskCompleted;
use App\States\MaintenanceTasks\TaskCreated;
use App\States\MaintenanceTasks\TaskRejected;
use App\States\MaintenanceTasks\TaskScheduled;
use App\States\MaintenanceTasks\TaskStarted;
use Illuminate\Validation\ValidationException;

class CancelMaintenanceOrderItem extends UpdateMaintenanceOrderItemStatus
{
    public function handle(): MaintenanceOrderItem
    {
        if (! in_array($this->currentStatus(), [
            MaintenanceOrderItemStatus::PendingOwnerApproval->value,
            MaintenanceOrderItemStatus::Scheduled->value,
            MaintenanceOrderItemStatus::InProgress->value,
            MaintenanceOrderItemStatus::Completed->value,
            MaintenanceOrderItemStatus::Rejected->value,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => __('api.validation.maintenance_order_items.cancel_current_status'),
            ]);
        }

        $attributes = $this->attributes;

        if (($attributes['cancelled_at'] ?? $this->model->cancelled_at) === null) {
            $attributes['cancelled_at'] = now();
        }

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        $this->cancelLinkedVehicleTask();

        return $this->model;
    }

    private function cancelLinkedVehicleTask(): void
    {
        $task = $this->model->maintenanceTask()->first();

        if ($task === null || $task->vehicle_id === null || $task->status->equals(TaskCancelled::class)) {
            return;
        }

        if (! $task->status->equals(
            TaskCreated::class,
            TaskScheduled::class,
            TaskStarted::class,
            TaskCompleted::class,
            TaskRejected::class,
        )) {
            return;
        }

        $task->status->transitionTo(TaskCancelled::class);
    }
}
