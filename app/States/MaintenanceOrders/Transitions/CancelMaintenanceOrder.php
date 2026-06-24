<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use Illuminate\Validation\ValidationException;

class CancelMaintenanceOrder extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        if (! in_array($this->currentStatus(), [
            MaintenanceOrderStatus::Scheduled->value,
            MaintenanceOrderStatus::InProgress->value,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only scheduled or in-progress orders can be cancelled.',
            ]);
        }

        $attributes = $this->attributes;

        if ($this->model->items()
            ->where('status', MaintenanceOrderItemStatus::InProgress->value)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'status' => 'An order with in-progress items cannot be cancelled.',
            ]);
        }

        if (($attributes['cancelled_at'] ?? $this->model->cancelled_at) === null) {
            $attributes['cancelled_at'] = now();
        }

        $items = $this->model->items()
            ->with('maintenanceTask')
            ->get();

        $this->model->items()
            ->where('status', '!=', MaintenanceOrderItemStatus::Cancelled->value)
            ->update([
                'status' => MaintenanceOrderItemStatus::Cancelled->value,
                'cancelled_at' => $attributes['cancelled_at'],
                'updated_at' => now(),
            ]);

        $this->cancelLinkedVehicleTasks($items);

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }

    /**
     * @param  iterable<int, MaintenanceOrderItem>  $items
     */
    private function cancelLinkedVehicleTasks(iterable $items): void
    {
        foreach ($items as $item) {
            $task = $item->maintenanceTask;

            if ($task === null || $task->vehicle_id === null || $task->status === MaintenanceTaskStatus::Cancelled) {
                continue;
            }

            if (! in_array($task->status, [
                MaintenanceTaskStatus::Created,
                MaintenanceTaskStatus::Scheduled,
                MaintenanceTaskStatus::Started,
                MaintenanceTaskStatus::Completed,
                MaintenanceTaskStatus::Rejected,
            ], true)) {
                continue;
            }

            $task->update(['status' => MaintenanceTaskStatus::Cancelled->value]);
        }
    }
}
