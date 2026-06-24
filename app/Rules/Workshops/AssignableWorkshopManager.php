<?php

namespace App\Rules\Workshops;

use App\Enums\SystemRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AssignableWorkshopManager implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $isWorkshopManager = User::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', SystemRole::WorkshopManager->value);
            })
            ->exists();

        if (! $isWorkshopManager) {
            $fail('The workshop manager must be an active user with the workshop_manager role.');
        }
    }
}
