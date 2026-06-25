<?php

namespace App\Policies;

use App\Enums\SystemRole;
use App\Models\User;

final class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(SystemRole::SuperAdmin->value);
    }
}
