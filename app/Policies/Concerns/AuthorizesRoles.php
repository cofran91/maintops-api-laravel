<?php

namespace App\Policies\Concerns;

use App\Enums\SystemRole;
use App\Models\User;

trait AuthorizesRoles
{
    /**
     * @param  array<int, SystemRole>  $roles
     * @return array<int, string>
     */
    protected function roleValues(array $roles): array
    {
        return array_map(
            static fn (SystemRole $role): string => $role->value,
            $roles,
        );
    }

    /**
     * @param  array<int, SystemRole>  $roles
     */
    protected function hasAnyRole(User $user, array $roles): bool
    {
        return $user->hasRole($this->roleValues($roles));
    }

    /**
     * @param  array<int, SystemRole>  $roles
     */
    protected function hasActiveRole(User $user, array $roles): bool
    {
        return $user->is_active && $this->hasAnyRole($user, $roles);
    }

    protected function isSystemAdmin(User $user): bool
    {
        return $this->hasAnyRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
        ]);
    }

    protected function isActiveSystemAdmin(User $user): bool
    {
        return $user->is_active && $this->isSystemAdmin($user);
    }
}
