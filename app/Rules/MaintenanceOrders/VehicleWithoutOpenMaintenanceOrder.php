<?php

namespace App\Rules\MaintenanceOrders;

use App\Enums\MaintenanceOrderStatus;
use App\Models\MaintenanceOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class VehicleWithoutOpenMaintenanceOrder implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $vehicleId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($vehicleId === false) {
            return;
        }

        $hasOpenOrder = MaintenanceOrder::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', [
                MaintenanceOrderStatus::Created->value,
                MaintenanceOrderStatus::PendingOwnerApproval->value,
                MaintenanceOrderStatus::PartiallyApproved->value,
                MaintenanceOrderStatus::Approved->value,
            ])
            ->exists();

        if ($hasOpenOrder) {
            $fail(__('api.validation.rules.vehicle_without_open_order'));
        }
    }
}
