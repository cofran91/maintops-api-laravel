<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Vehicle;

final class VehiclePolicy
{
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
        return $user->is_active && $this->isSystemAdmin($user);
    }

    public function import(User $user): bool
    {
        return $user->is_active && $this->isSystemAdmin($user);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->is_active && $this->isSystemAdmin($user);
    }

    /**
     * Vehicle records are visible to administrative and front-office roles.
     * Technicians do not manage vehicle master data in this module.
     */
    private function canReadVehicleRecords(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->hasRole($this->roleValues([
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
        ]));
    }

    /**
     * Advisors can register intake data, while edits are reserved for admins
     * and workshop managers.
     */
    private function canManageVehicleRecords(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->hasRole($this->roleValues([
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
        ]));
    }

    /**
     * Destructive vehicle operations are limited to system administrators.
     */
    private function isSystemAdmin(User $user): bool
    {
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
