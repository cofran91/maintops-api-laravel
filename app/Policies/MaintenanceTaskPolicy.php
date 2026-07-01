<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Policies\Concerns\AuthorizesRoles;

final class MaintenanceTaskPolicy
{
    use AuthorizesRoles;

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
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::Advisor,
        ]);
    }

    /**
     * Task catalog changes are reserved for system administrators.
     */
    private function canManageMaintenanceTasks(User $user): bool
    {
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
        ]);
    }
}
