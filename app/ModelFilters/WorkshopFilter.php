<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class WorkshopFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'name',
        'code',
        'city',
        'email',
        'phone',
        'is_active',
        'manager_user_id',
        'vehicle_system_id',
        'created_from',
        'created_to',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('name', 'like', '%'.$value.'%')
                ->orWhere('code', 'like', '%'.$value.'%')
                ->orWhere('city', 'like', '%'.$value.'%')
                ->orWhere('email', 'like', '%'.$value.'%')
                ->orWhere('phone', 'like', '%'.$value.'%')
                ->orWhereHas('manager', function (Builder $query) use ($value): void {
                    $query
                        ->where('name', 'like', '%'.$value.'%')
                        ->orWhere('email', 'like', '%'.$value.'%');
                });
        });
    }

    public function name(string $value): void
    {
        $this->where('name', 'like', '%'.$value.'%');
    }

    public function code(string $value): void
    {
        $this->where('code', 'like', '%'.Str::upper($value).'%');
    }

    public function city(string $value): void
    {
        $this->where('city', 'like', '%'.$value.'%');
    }

    public function email(string $value): void
    {
        $this->where('email', 'like', '%'.$value.'%');
    }

    public function phone(string $value): void
    {
        $this->where('phone', 'like', '%'.$value.'%');
    }

    public function isActive(bool|int|string $value): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($isActive === null) {
            return;
        }

        $this->where('is_active', $isActive);
    }

    public function managerUserId(int|string $value): void
    {
        $this->where('manager_user_id', $value);
    }

    public function vehicleSystemId(int|string $value): void
    {
        $this->whereHas('vehicleSystems', function (Builder $query) use ($value): void {
            $query->where('vehicle_systems.id', $value);
        });
    }

    public function createdFrom(string $value): void
    {
        $date = $this->dateFilterValue($value);

        if ($date === null) {
            return;
        }

        $this->where('created_at', '>=', $date->startOfDay());
    }

    public function createdTo(string $value): void
    {
        $date = $this->dateFilterValue($value);

        if ($date === null) {
            return;
        }

        $this->where('created_at', '<=', $date->endOfDay());
    }
}
