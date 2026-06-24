<?php

namespace App\Rules\MaintenanceOrders;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use App\States\MaintenanceOrderItems\MaintenanceOrderItemState;
use App\States\MaintenanceOrderItems\OrderItemInProgress;
use App\States\MaintenanceOrderItems\OrderItemScheduled;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AllowedMaintenanceOrderItemStatus implements ValidationRule
{
    public function __construct(
        private readonly ?User $actor,
        private readonly ?MaintenanceOrderItem $item,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $status = is_string($value) ? MaintenanceOrderItemStatus::tryFrom($value) : null;

        if (! $status instanceof MaintenanceOrderItemStatus) {
            return;
        }

        if (! in_array($value, $this->allowedStatuses(), true)) {
            $fail('The authenticated role cannot apply this item status change.');

            return;
        }

        if (
            $status === MaintenanceOrderItemStatus::Cancelled
            && $this->item instanceof MaintenanceOrderItem
            && ! $this->item->status->equals(OrderItemScheduled::class, OrderItemInProgress::class)
        ) {
            $fail('Only scheduled or in-progress items can be cancelled from this endpoint.');

            return;
        }

        if (
            $this->item instanceof MaintenanceOrderItem
            && ! $this->item->status->canTransitionTo(
                MaintenanceOrderItemState::resolveStateClass($status->value),
            )
        ) {
            $fail('The requested item status transition is not allowed.');
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
                MaintenanceOrderItemStatus::InProgress->value,
                MaintenanceOrderItemStatus::Completed->value,
                MaintenanceOrderItemStatus::Rejected->value,
                MaintenanceOrderItemStatus::Cancelled->value,
            ],
            $this->actor->hasRole(SystemRole::WorkshopManager->value) => [
                MaintenanceOrderItemStatus::Cancelled->value,
            ],
            $this->actor->hasRole(SystemRole::Advisor->value) => [
                MaintenanceOrderItemStatus::Rejected->value,
            ],
            $this->actor->hasRole(SystemRole::Technician->value)
                && ! $this->actor->hasRole(SystemRole::Advisor->value) => [
                    MaintenanceOrderItemStatus::InProgress->value,
                    MaintenanceOrderItemStatus::Completed->value,
                ],
            default => [],
        };
    }
}
