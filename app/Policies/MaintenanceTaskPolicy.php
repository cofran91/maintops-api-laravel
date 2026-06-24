<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\MaintenanceTask;
use App\Models\User;

final class MaintenanceTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewMaintenanceTasks($user);
    }

    public function view(User $user, MaintenanceTask $maintenanceTask): bool
    {
        return $this->canViewMaintenanceTasks($user);
    }

    public function create(User $user): bool
    {
        return $this->canViewMaintenanceTasks($user);
    }

    public function update(User $user, MaintenanceTask $maintenanceTask): bool
    {
        return $this->canManageMaintenanceTasks($user);
    }

    public function delete(User $user, MaintenanceTask $maintenanceTask): bool
    {
        return $this->canManageMaintenanceTasks($user);
    }

    /**
     * Maintenance tasks are visible to system admins and advisors. Workshop
     * managers receive operational work later through orders, not task catalogs.
     */
    private function canViewMaintenanceTasks(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->hasRole($this->roleValues([
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::Advisor,
        ]));
    }

    /**
     * Task catalog changes are reserved for system administrators.
     */
    private function canManageMaintenanceTasks(User $user): bool
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
