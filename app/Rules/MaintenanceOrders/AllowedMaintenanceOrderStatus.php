<?php

namespace App\Rules\MaintenanceOrders;

use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\States\MaintenanceOrders\MaintenanceOrderState;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AllowedMaintenanceOrderStatus implements ValidationRule
{
    public function __construct(
        private readonly ?User $actor,
        private readonly ?MaintenanceOrder $maintenanceOrder = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $status = is_string($value) ? MaintenanceOrderStatus::tryFrom($value) : null;

        if (! $status instanceof MaintenanceOrderStatus) {
            return;
        }

        if (! in_array($value, $this->allowedStatuses(), true)) {
            $fail(__('api.validation.rules.order_status_role'));

            return;
        }

        if (
            $this->maintenanceOrder instanceof MaintenanceOrder
            && ! $this->maintenanceOrder->status->canTransitionTo(
                MaintenanceOrderState::resolveStateClass($status->value),
            )
        ) {
            $fail(__('api.validation.rules.order_status_transition'));
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        if (! $this->actor instanceof User) {
            return [];
        }

        return match (true) {
            $this->actor->hasRole([
                SystemRole::SuperAdmin->value,
                SystemRole::Admin->value,
            ]) => [
                MaintenanceOrderStatus::Approved->value,
                MaintenanceOrderStatus::Cancelled->value,
                MaintenanceOrderStatus::Delivered->value,
                MaintenanceOrderStatus::Rejected->value,
            ],
            $this->actor->hasRole(SystemRole::WorkshopManager->value) => [
                MaintenanceOrderStatus::Cancelled->value,
            ],
            $this->actor->hasRole(SystemRole::Advisor->value) => [
                MaintenanceOrderStatus::Approved->value,
                MaintenanceOrderStatus::Rejected->value,
            ],
            default => [],
        };
    }
}
