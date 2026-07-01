<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\User;
use App\Policies\Concerns\AuthorizesRoles;

final class UserPolicy
{
    use AuthorizesRoles;

    public function viewAny(User $user): bool
    {
        return $this->isSystemAdmin($user)
            || $this->hasAnyRole($user, [SystemRole::WorkshopManager]);
    }

    public function view(User $user, User $target): bool
    {
        return $this->canViewUser($user, $target);
    }

    public function create(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
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
            $this->hasAnyRole($actor, [SystemRole::WorkshopManager])
            && $this->hasAnyRole($target, [SystemRole::Technician])
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
        return $this->isActiveSystemAdmin($actor);
    }
}
