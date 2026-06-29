<?php

namespace App\Rules\Workshops;

use App\Enums\SystemRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AssignableWorkshopTechnician implements ValidationRule
{
    public function __construct(
        private readonly int|string|null $workshopId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            return;
        }

        $technician = User::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', SystemRole::Technician->value);
            })
            ->first();

        if (! $technician instanceof User) {
            $fail(__('api.validation.rules.technician_active_role'));

            return;
        }

        if ($technician->workshop_id !== null && (string) $technician->workshop_id !== (string) $this->workshopId) {
            $fail(__('api.validation.rules.technician_assigned_elsewhere'));
        }
    }
}
