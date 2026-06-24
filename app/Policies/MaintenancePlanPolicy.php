<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\MaintenancePlan;
use App\Models\User;

final class MaintenancePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageMaintenancePlans($user);
    }

    public function view(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $this->canManageMaintenancePlans($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageMaintenancePlans($user);
    }

    public function update(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $this->canManageMaintenancePlans($user);
    }

    public function delete(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $this->canManageMaintenancePlans($user);
    }

    /**
     * Plans define reusable operational standards, so only system admins manage
     * this catalog. Role-specific execution visibility comes later from orders.
     */
    private function canManageMaintenancePlans(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->hasRole($this->roleValues([
            SystemRole::SuperAdmin,
            SystemRole::Admin,
        ]));
    }

    /**
     * @param  array<int, SystemRole>  $roles
     * @return array<int, string>
     */
    private function roleValues(array $roles): array
    {
        return array_map(
            static fn (SystemRole $role): string => $role->value,
            $roles,
        );
    }
}
