<?php

namespace App\Rules\Workshops;

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Workshop;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AssignableWorkshopManager implements ValidationRule
{
    public function __construct(
        private readonly ?Workshop $currentWorkshop = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $manager = User::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', SystemRole::WorkshopManager->value);
            })
            ->first();

        if (! $manager instanceof User) {
            $fail(__('api.validation.rules.manager_active_role'));

            return;
        }

        $assignedWorkshopQuery = Workshop::query()
            ->where('manager_user_id', $manager->getKey());

        if ($this->currentWorkshop instanceof Workshop) {
            $assignedWorkshopQuery->where('id', '<>', $this->currentWorkshop->getKey());
        }

        if ($assignedWorkshopQuery->exists()) {
            $fail(__('api.validation.rules.manager_assigned_elsewhere'));
        }
    }
}
