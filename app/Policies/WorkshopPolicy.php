<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workshop;
use App\Policies\Concerns\AuthorizesRoles;

final class WorkshopPolicy
{
    use AuthorizesRoles;

    public function viewAny(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function view(User $user, Workshop $workshop): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function export(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function import(User $user): bool
    {
        return $this->isActiveSystemAdmin($user);
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $this->isActiveSystemAdmin($user);
    }
}
