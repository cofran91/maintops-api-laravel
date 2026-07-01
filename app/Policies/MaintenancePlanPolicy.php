<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Policies\Concerns\AuthorizesRoles;

final class MaintenancePlanPolicy
{
    use AuthorizesRoles;

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
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
        ]);
    }
}
