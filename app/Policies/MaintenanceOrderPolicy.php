<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\User;

final class MaintenanceOrderPolicy
{
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
        return $user->is_active
            && $user->hasRole($this->roleValues([
                SystemRole::SuperAdmin,
                SystemRole::Admin,
            ]));
    }

    private function isAssignedWorkshopManager(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        if (! $user->is_active || ! $user->hasRole(SystemRole::WorkshopManager->value)) {
            return false;
        }

        if ($user->hasRole($this->roleValues([
            SystemRole::SuperAdmin,
            SystemRole::Admin,
        ]))) {
            return false;
        }

        return $maintenanceOrder->workshop()
            ->where('manager_user_id', $user->getKey())
            ->exists();
    }

    private function isAssignedTechnician(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        if (! $user->is_active || ! $user->hasRole(SystemRole::Technician->value)) {
            return false;
        }

        if ($user->hasRole($this->roleValues([
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
        ]))) {
            return false;
        }

        return (int) $maintenanceOrder->technician_id === (int) $user->getKey();
    }

    private function isPrimaryAdvisor(User $user): bool
    {
        return $user->is_active
            && $user->hasRole(SystemRole::Advisor->value)
            && ! $user->hasRole($this->roleValues([
                SystemRole::SuperAdmin,
                SystemRole::Admin,
                SystemRole::WorkshopManager,
            ]));
    }

    private function isActiveAllowedRole(User $user): bool
    {
        return $user->is_active
            && $user->hasRole($this->roleValues([
                SystemRole::SuperAdmin,
                SystemRole::Admin,
                SystemRole::WorkshopManager,
                SystemRole::Advisor,
                SystemRole::Technician,
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
