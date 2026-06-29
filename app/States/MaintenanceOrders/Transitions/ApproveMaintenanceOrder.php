<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrder;
use App\States\MaintenanceOrders\OrderApproved;
use App\States\MaintenanceOrders\OrderPartiallyApproved;
use App\States\MaintenanceOrders\OrderRejected;
use Illuminate\Validation\ValidationException;

class ApproveMaintenanceOrder extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        $totalItems = $this->model->items()->count();

        if ($totalItems === 0) {
            throw ValidationException::withMessages([
                'status' => __('api.validation.maintenance_orders.approve_without_items'),
            ]);
        }

        $rejectedItems = $this->model->items()
            ->where('status', MaintenanceOrderItemStatus::Rejected->value)
            ->count();

        $this->model->fill($this->attributes);
        $this->model->{$this->field} = match (true) {
            $rejectedItems === $totalItems => OrderRejected::class,
            $rejectedItems > 0 => OrderPartiallyApproved::class,
            default => OrderApproved::class,
        };
        $this->model->save();

        return $this->model;
    }
}
