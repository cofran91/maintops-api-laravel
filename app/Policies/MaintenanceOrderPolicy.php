<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesRoles;

final class MaintenanceOrderPolicy
{
    use AuthorizesRoles;

    public function viewAny(User $user): bool
    {
        return $this->isActiveAllowedRole($user);
    }

    public function view(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return $this->canManageAnyOrder($user)
            || $this->isPrimaryAdvisor($user)
            || $this->isAssignedWorkshopManager($user, $maintenanceOrder)
            || $this->isAssignedTechnician($user, $maintenanceOrder);
    }

    public function create(User $user): bool
    {
        return $this->canManageAnyOrder($user)
            || $this->isPrimaryAdvisor($user);
    }

    public function update(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return $this->canManageAnyOrder($user)
            || $this->isPrimaryAdvisor($user)
            || $this->isAssignedWorkshopManager($user, $maintenanceOrder);
    }

    private function canManageAnyOrder(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    private function isAssignedWorkshopManager(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        if (! $this->hasActiveRole($user, [SystemRole::WorkshopManager])) {
            return false;
        }

        if ($this->isSystemAdmin($user)) {
            return false;
        }

        return $maintenanceOrder->workshop()
            ->where('manager_user_id', $user->getKey())
            ->exists();
    }

    private function isAssignedTechnician(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        if (! $this->hasActiveRole($user, [SystemRole::Technician])) {
            return false;
        }

        if ($this->hasAnyRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
        ])) {
            return false;
        }

        return (int) $maintenanceOrder->technician_id === (int) $user->getKey();
    }

    private function isPrimaryAdvisor(User $user): bool
    {
        return $this->hasActiveRole($user, [SystemRole::Advisor])
            && ! $this->hasAnyRole($user, [
                SystemRole::SuperAdmin,
                SystemRole::Admin,
                SystemRole::WorkshopManager,
            ]);
    }

    private function isActiveAllowedRole(User $user): bool
    {
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
            SystemRole::Technician,
        ]);
    }
}
