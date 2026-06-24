<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Models\MaintenanceOrder;

class StartMaintenanceOrder extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        $attributes = $this->attributes;

        if (($attributes['started_at'] ?? $this->model->started_at) === null) {
            $attributes['started_at'] = now();
        }

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }
}
