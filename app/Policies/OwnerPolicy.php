<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\Owner;
use App\Models\User;

final class OwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageOwnerRecords($user);
    }

    public function view(User $user, Owner $owner): bool
    {
        return $this->canManageOwnerRecords($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageOwnerRecords($user);
    }

    public function update(User $user, Owner $owner): bool
    {
        return $this->canManageOwnerRecords($user);
    }

    public function delete(User $user, Owner $owner): bool
    {
        return $user->is_active && $this->isSystemAdmin($user);
    }

    /**
     * Owners are operational contacts, so admins and front-office roles can keep
     * their records updated. Technicians do not manage owner master data.
     */
    private function canManageOwnerRecords(User $user): bool
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
     * Destructive owner operations are limited to system administrators.
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
