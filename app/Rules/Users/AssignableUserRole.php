<?php

namespace App\Rules\Users;

use App\Enums\SystemRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AssignableUserRole implements ValidationRule
{
    public function __construct(
        private readonly ?User $actor,
        private readonly ?User $target = null,
        private readonly bool $creating = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $role = SystemRole::tryFrom($value);

        if (! $role instanceof SystemRole) {
            return;
        }

        if ($this->isCreatingSuperAdmin($role) || $this->isPromotingToSuperAdmin($role)) {
            $fail('Super admin users cannot be created or promoted through this endpoint.');

            return;
        }

        if (! $this->actor instanceof User || ! $this->actorCanAssign($role)) {
            $fail('You are not allowed to assign the selected role.');
        }
    }

    private function actorCanAssign(SystemRole $role): bool
    {
        if (! $this->actor instanceof User || ! $this->actor->is_active) {
            return false;
        }

        return $this->actor->hasRole([
            SystemRole::SuperAdmin->value,
            SystemRole::Admin->value,
        ]);
    }

    private function isCreatingSuperAdmin(SystemRole $role): bool
    {
        return $this->creating && $role === SystemRole::SuperAdmin;
    }

    private function isPromotingToSuperAdmin(SystemRole $role): bool
    {
        return ! $this->creating
            && $role === SystemRole::SuperAdmin
            && ! $this->target?->hasRole(SystemRole::SuperAdmin->value);
    }
}
