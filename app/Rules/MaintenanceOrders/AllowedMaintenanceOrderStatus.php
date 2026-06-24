<?php

namespace App\Rules\MaintenanceOrders;

use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AllowedMaintenanceOrderStatus implements ValidationRule
{
    public function __construct(
        private readonly ?User $actor,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $status = is_string($value) ? MaintenanceOrderStatus::tryFrom($value) : null;

        if (! $status instanceof MaintenanceOrderStatus) {
            return;
        }

        if (! in_array($value, $this->allowedStatuses(), true)) {
            $fail('The authenticated role cannot apply this order status change.');
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
