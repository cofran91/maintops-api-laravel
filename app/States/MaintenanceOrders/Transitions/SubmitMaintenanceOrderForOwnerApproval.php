<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrder;
use Illuminate\Validation\ValidationException;

class SubmitMaintenanceOrderForOwnerApproval extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        if (! $this->model->items()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('api.validation.maintenance_orders.submit_without_items'),
            ]);
        }

        $pendingAt = now();

        $this->model->items()
            ->where('status', '!=', MaintenanceOrderItemStatus::PendingOwnerApproval->value)
            ->update([
                'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                'pending_owner_approval_at' => $pendingAt,
                'updated_at' => now(),
            ]);

        $this->model->items()
            ->where('status', MaintenanceOrderItemStatus::PendingOwnerApproval->value)
            ->whereNull('pending_owner_approval_at')
            ->update([
                'pending_owner_approval_at' => $pendingAt,
                'updated_at' => now(),
            ]);

        $this->model->fill($this->attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }
}
