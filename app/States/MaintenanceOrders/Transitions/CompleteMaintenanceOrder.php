<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrder;
use Illuminate\Validation\ValidationException;

class CompleteMaintenanceOrder extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        $attributes = $this->attributes;

        if (($attributes['started_at'] ?? $this->model->started_at) === null) {
            throw ValidationException::withMessages([
                'status' => 'An order cannot be completed before it has started.',
            ]);
        }

        if ($this->model->items()
            ->whereIn('status', [
                MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                MaintenanceOrderItemStatus::Scheduled->value,
                MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'status' => 'An order with pending or active items cannot be completed.',
            ]);
        }

        if (($attributes['finished_at'] ?? $this->model->finished_at) === null) {
            $attributes['finished_at'] = now();
        }

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }
}
