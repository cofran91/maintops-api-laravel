<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\Concerns\AuthorizesRoles;

final class VehiclePolicy
{
    use AuthorizesRoles;

    public function viewAny(User $user): bool
    {
        return $this->canReadVehicleRecords($user);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->canReadVehicleRecords($user);
    }

    public function create(User $user): bool
    {
        return $this->canReadVehicleRecords($user);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->canManageVehicleRecords($user);
    }

    public function export(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function import(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    /**
     * Vehicle records are visible to administrative and front-office roles.
     * Technicians do not manage vehicle master data in this module.
     */
    private function canReadVehicleRecords(User $user): bool
    {
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
        ]);
    }

    /**
     * Advisors can register intake data, while edits are reserved for admins
     * and workshop managers.
     */
    private function canManageVehicleRecords(User $user): bool
    {
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
        ]);
    }
}
