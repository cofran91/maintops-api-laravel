<?php

namespace App\Actions\MaintenanceOrders;

use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceOrderItems\MaintenanceOrderItemState;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

final class UpdateMaintenanceOrderItemAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(MaintenanceOrderItem $item, array $attributes): MaintenanceOrderItem
    {
        return DB::transaction(function () use ($item, $attributes): MaintenanceOrderItem {
            if (! array_key_exists('status', $attributes)) {
                $item->update($attributes);

                return $item->refresh();
            }

            $targetState = MaintenanceOrderItemState::resolveStateClass($attributes['status']);
            $attributesWithoutStatus = Arr::except($attributes, ['status']);

            if ($item->status->equals($targetState)) {
                $item->update($attributesWithoutStatus);

                return $item->refresh();
            }

            try {
                $item->status->transitionTo($targetState, $attributesWithoutStatus);
            } catch (CouldNotPerformTransition) {
                throw ValidationException::withMessages([
                    'status' => __('api.validation.maintenance_order_items.transition_invalid'),
                ]);
            }

            return $item->refresh();
        });
    }
}
