<?php

namespace App\Rules\Users;

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Workshop;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AssignableUserWorkshop implements ValidationRule
{
    public function __construct(
        private readonly ?User $actor,
        private readonly string $roleValue,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $workshopId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($workshopId === false || ! $this->workshopExists($workshopId)) {
            $fail(__('api.validation.rules.selected_workshop_invalid'));

            return;
        }

        if ($this->roleValue !== SystemRole::Technician->value) {
            $fail(__('api.validation.rules.user_workshop_requires_technician'));

            return;
        }

        if (! $this->actorCanAssignWorkshop()) {
            $fail(__('api.validation.rules.user_workshop_admin_only'));
        }
    }

    private function actorCanAssignWorkshop(): bool
    {
        if (! $this->actor instanceof User) {
            return false;
        }

        return $this->actor->hasRole([
            SystemRole::SuperAdmin->value,
            SystemRole::Admin->value,
        ]);
    }

    private function workshopExists(int $workshopId): bool
    {
        return Workshop::query()
            ->whereKey($workshopId)
            ->exists();
    }
}
