<?php

namespace App\ModelFilters;

use App\Enums\MaintenanceTaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class MaintenanceTaskFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'name',
        'code',
        'vehicle_id',
        'without_vehicle',
        'vehicle_system_id',
        'status',
        'is_active',
        'estimated_duration_from',
        'estimated_duration_to',
        'created_from',
        'created_to',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('name', 'like', '%'.$value.'%')
                ->orWhere('code', 'like', '%'.Str::upper($value).'%')
                ->orWhere('description', 'like', '%'.$value.'%')
                ->orWhereHas('vehicle', function (Builder $query) use ($value): void {
                    $query
                        ->where('license_plate', 'like', '%'.Str::upper($value).'%')
                        ->orWhere('brand', 'like', '%'.$value.'%')
                        ->orWhere('model', 'like', '%'.$value.'%');
                })
                ->orWhereHas('vehicleSystem', function (Builder $query) use ($value): void {
                    $query
                        ->where('code', 'like', '%'.Str::upper($value).'%')
                        ->orWhere('name', 'like', '%'.$value.'%');
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

    public function vehicleId(int|string $value): void
    {
        $normalizedValue = strtolower(trim((string) $value));

        if (in_array($normalizedValue, ['null', 'none', 'unassigned'], true)) {
            $this->whereNull('vehicle_id');

            return;
        }

        $vehicleId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($vehicleId === false) {
            return;
        }

        $this->where('vehicle_id', $vehicleId);
    }

    public function withoutVehicle(bool|int|string $value): void
    {
        $withoutVehicle = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($withoutVehicle !== true) {
            return;
        }

        $this->whereNull('vehicle_id');
    }

    public function vehicleSystemId(int|string $value): void
    {
        $vehicleSystemId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($vehicleSystemId === false) {
            return;
        }

        $this->where('vehicle_system_id', $vehicleSystemId);
    }

    public function status(string $value): void
    {
        if (! in_array($value, MaintenanceTaskStatus::values(), true)) {
            return;
        }

        $this->where('status', $value);
    }

    public function isActive(bool|int|string $value): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($isActive === null) {
            return;
        }

        $this->where('is_active', $isActive);
    }

    public function estimatedDurationFrom(int|string $value): void
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($minutes === false) {
            return;
        }

        $this->where('estimated_duration_minutes', '>=', $minutes);
    }

    public function estimatedDurationTo(int|string $value): void
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($minutes === false) {
            return;
        }

        $this->where('estimated_duration_minutes', '<=', $minutes);
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
