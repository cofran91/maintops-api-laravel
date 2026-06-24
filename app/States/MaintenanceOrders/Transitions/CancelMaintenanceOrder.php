<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Models\MaintenanceOrder;
use App\States\MaintenanceOrderItems\OrderItemCancelled;
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

        $items
            ->reject(fn ($item): bool => $item->status->equals(OrderItemCancelled::class))
            ->each(fn ($item): mixed => $item->status->transitionTo(OrderItemCancelled::class, [
                'cancelled_at' => $attributes['cancelled_at'],
            ]));

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }
}
