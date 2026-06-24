<?php

namespace App\ModelFilters;

use App\Enums\SystemRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Builder;

final class UserFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'role',
        'workshop_id',
        'is_active',
        'without_workshop',
    ];

    public function setup(): void
    {
        $this->whereHasVisibleRoles();
        $this->whereMatchesManagedWorkshop();
    }

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('name', 'like', '%'.$value.'%')
                ->orWhere('email', 'like', '%'.$value.'%')
                ->orWhere('phone', 'like', '%'.$value.'%')
                ->orWhere('document_number', 'like', '%'.$value.'%');
        });
    }

    public function role(string $value): void
    {
        $visibleRoles = $this->visibleRoleValues();

        if (! in_array($value, $visibleRoles, true)) {
            $this->whereRaw('1 = 0');

            return;
        }

        $this->whereHas('roles', function (Builder $query) use ($value): void {
            $query->where('name', $value);
        });
    }

    public function isActive(bool|int|string $value): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($isActive === null) {
            return;
        }

        $this->where('is_active', $isActive);
    }

    public function workshopId(int|string $value): void
    {
        $normalizedValue = strtolower(trim((string) $value));

        if (in_array($normalizedValue, ['null', 'none', 'unassigned'], true)) {
            $this->whereNull('workshop_id');

            return;
        }

        $workshopId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($workshopId === false) {
            return;
        }

        $this->where('workshop_id', $workshopId);
    }

    public function withoutWorkshop(bool|int|string $value): void
    {
        $withoutWorkshop = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($withoutWorkshop !== true) {
            return;
        }

        $this
            ->whereNull('workshop_id')
            ->whereDoesntHave('managedWorkshop');
    }

    /**
     * Applies the base role visibility scope for the authenticated user.
     *
     * The policy decides whether the list endpoint is accessible; this filter
     * decides which roles can appear in the result set once access is granted.
     */
    private function whereHasVisibleRoles(): void
    {
        $visibleRoles = $this->visibleRoleValues();

        if ($visibleRoles === []) {
            $this->whereRaw('1 = 0');

            return;
        }

        $this->whereHas('roles', function (Builder $query) use ($visibleRoles): void {
            $query->whereIn('name', $visibleRoles);
        });
    }

    /**
     * Limits workshop managers to the technicians assigned to their workshop.
     *
     * System admins keep the broader role-based visibility resolved above.
     */
    private function whereMatchesManagedWorkshop(): void
    {
        $user = request()->user();

        if (
            ! $user instanceof User
            || $user->hasRole([
                SystemRole::SuperAdmin->value,
                SystemRole::Admin->value,
            ])
            || ! $user->hasRole(SystemRole::WorkshopManager->value)
        ) {
            return;
        }

        $managedWorkshopId = $user->managedWorkshop()->value('id');

        if ($managedWorkshopId === null) {
            $this->whereRaw('1 = 0');

            return;
        }

        $this->where('workshop_id', $managedWorkshopId);
    }

    /**
     * Resolves the role names the authenticated user is allowed to see.
     *
     * @see UserPolicy::viewAny()
     *
     * @return array<int, string>
     */
    private function visibleRoleValues(): array
    {
        $user = request()->user();

        if (! $user instanceof User) {
            return [];
        }

        return match (true) {
            $user->hasRole([
                SystemRole::SuperAdmin->value,
                SystemRole::Admin->value,
            ]) => SystemRole::values(),
            $user->hasRole(SystemRole::WorkshopManager->value) => [
                SystemRole::Technician->value,
            ],
            default => [],
        };
    }
}
