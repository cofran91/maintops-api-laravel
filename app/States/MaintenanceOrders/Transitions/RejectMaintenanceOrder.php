<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use Illuminate\Validation\ValidationException;

class RejectMaintenanceOrder extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        if ($this->model->scheduled_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'A scheduled order cannot be rejected.',
            ]);
        }

        $items = $this->model->items()
            ->with('maintenanceTask')
            ->get();
        $rejectedAt = now();

        $this->model->items()
            ->where('status', '!=', MaintenanceOrderItemStatus::Rejected->value)
            ->update([
                'status' => MaintenanceOrderItemStatus::Rejected->value,
                'rejected_at' => $rejectedAt,
                'updated_at' => now(),
            ]);

        $this->rejectLinkedVehicleTasks($items);

        $this->model->fill($this->attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }

    /**
     * @param  iterable<int, MaintenanceOrderItem>  $items
     */
    private function rejectLinkedVehicleTasks(iterable $items): void
    {
        foreach ($items as $item) {
            $task = $item->maintenanceTask;

            if ($task === null || $task->vehicle_id === null || $task->status === MaintenanceTaskStatus::Rejected) {
                continue;
            }

            if (! in_array($task->status, [
                MaintenanceTaskStatus::Created,
                MaintenanceTaskStatus::Scheduled,
            ], true)) {
                continue;
            }

            $task->update(['status' => MaintenanceTaskStatus::Rejected->value]);
        }
    }
}
