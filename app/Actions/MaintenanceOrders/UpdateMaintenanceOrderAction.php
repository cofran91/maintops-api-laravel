<?php

namespace App\Actions\MaintenanceOrders;

use App\Models\MaintenanceOrder;
use App\States\MaintenanceOrders\MaintenanceOrderState;
use App\States\MaintenanceOrders\OrderApproved;
use App\States\MaintenanceOrders\OrderPartiallyApproved;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

final class UpdateMaintenanceOrderAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(MaintenanceOrder $maintenanceOrder, array $attributes): MaintenanceOrder
    {
        return DB::transaction(function () use ($maintenanceOrder, $attributes): MaintenanceOrder {
            if (! array_key_exists('status', $attributes)) {
                $maintenanceOrder->update($attributes);

                return $maintenanceOrder->refresh();
            }

            $targetState = MaintenanceOrderState::resolveStateClass($attributes['status']);
            $attributesWithoutStatus = Arr::except($attributes, ['status']);

            if ($targetState === OrderPartiallyApproved::class) {
                $targetState = OrderApproved::class;
            }

            if ($maintenanceOrder->status->equals($targetState)) {
                $maintenanceOrder->update($attributesWithoutStatus);

                return $maintenanceOrder->refresh();
            }

            try {
                $maintenanceOrder->status->transitionTo($targetState, $attributesWithoutStatus);
            } catch (CouldNotPerformTransition) {
                throw ValidationException::withMessages([
                    'status' => __('api.validation.maintenance_orders.transition_invalid'),
                ]);
            }

            return $maintenanceOrder->refresh();
        });
    }
}
