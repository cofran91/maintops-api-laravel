<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Models\MaintenanceOrder;
use App\States\MaintenanceOrderItems\OrderItemRejected;
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

        $items
            ->reject(fn ($item): bool => $item->status->equals(OrderItemRejected::class))
            ->each(fn ($item): mixed => $item->status->transitionTo(OrderItemRejected::class, [
                'rejected_at' => $rejectedAt,
            ]));

        $this->model->fill($this->attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }
}
