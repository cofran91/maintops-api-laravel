<?php

namespace App\Rules\MaintenanceOrders;

use App\Enums\SystemRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ActiveMaintenanceOrderAdvisor implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $advisor = User::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', SystemRole::Advisor->value);
            })
            ->first();

        if (! $advisor instanceof User) {
            $fail(__('api.validation.rules.active_advisor'));
        }
    }
}
