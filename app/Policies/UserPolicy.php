<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSystemAdmin($user)
            || $user->hasRole(SystemRole::WorkshopManager->value);
    }

    public function view(User $user, User $target): bool
    {
        return $this->canViewUser($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $this->isSystemAdmin($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->canManageUser($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target)
            && $this->canManageUser($user, $target);
    }

    /**
     * System admins can inspect every user; workshop managers can inspect only
     * technicians assigned to the workshop they manage. Advisors and technicians
     * do not have user visibility.
     */
    private function canViewUser(User $actor, User $target): bool
    {
        if ($this->isSystemAdmin($actor)) {
            return true;
        }

        if (
            $actor->hasRole(SystemRole::WorkshopManager->value)
            && $target->hasRole(SystemRole::Technician->value)
            && $target->workshop_id !== null
        ) {
            return $actor
                ->managedWorkshop()
                ->whereKey($target->workshop_id)
                ->exists();
        }

        return false;
    }

    /**
     * Only active system admins can mutate users. Workshop managers can view
     * assigned technician records, but cannot create, update, or delete users.
     */
    private function canManageUser(User $actor, User $target): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        return $this->isSystemAdmin($actor);
    }

    /**
     * Groups the two administrative roles that share full user-management access.
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
