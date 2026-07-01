<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Workshop;

final class WorkshopPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSystemAdmin($user);
    }

    public function view(User $user, Workshop $workshop): bool
    {
        return $this->isSystemAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSystemAdmin($user);
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $this->isSystemAdmin($user);
    }

    public function export(User $user): bool
    {
        return $this->isSystemAdmin($user);
    }

    public function import(User $user): bool
    {
        return $this->isSystemAdmin($user);
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $this->isSystemAdmin($user);
    }

    /**
     * Workshop master data is managed only by the two system administrator roles.
     */
    private function isSystemAdmin(User $user): bool
    {
        return $user->is_active
            && $user->hasRole($this->roleValues([
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
