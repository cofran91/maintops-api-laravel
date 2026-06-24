<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrderItem;
use Illuminate\Validation\ValidationException;

class CancelMaintenanceOrderItem extends UpdateMaintenanceOrderItemStatus
{
    public function handle(): MaintenanceOrderItem
    {
        if (! in_array($this->currentStatus(), [
            MaintenanceOrderItemStatus::Scheduled->value,
            MaintenanceOrderItemStatus::InProgress->value,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only scheduled or in-progress items can be cancelled.',
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

        if ($task === null || $task->vehicle_id === null || $task->status === MaintenanceTaskStatus::Cancelled) {
            return;
        }

        if (! in_array($task->status, [
            MaintenanceTaskStatus::Created,
            MaintenanceTaskStatus::Scheduled,
            MaintenanceTaskStatus::Started,
        ], true)) {
            return;
        }

        $task->update(['status' => MaintenanceTaskStatus::Cancelled->value]);
    }
}
