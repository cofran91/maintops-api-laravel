<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\Owner;
use App\Models\User;
use App\Policies\Concerns\AuthorizesRoles;

final class OwnerPolicy
{
    use AuthorizesRoles;

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

    public function export(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function import(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function delete(User $user, Owner $owner): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    /**
     * Owners are operational contacts, so admins and front-office roles can keep
     * their records updated. Technicians do not manage owner master data.
     */
    private function canManageOwnerRecords(User $user): bool
    {
        return $this->hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
        ]);
    }
}
