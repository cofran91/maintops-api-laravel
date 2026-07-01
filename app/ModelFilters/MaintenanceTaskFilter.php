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
        $this->whereNullableInteger('vehicle_id', $value);
    }

    public function withoutVehicle(bool|int|string $value): void
    {
        $this->whereBooleanNull('vehicle_id', $value);
    }

    public function vehicleSystemId(int|string $value): void
    {
        $this->whereInteger('vehicle_system_id', $value);
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
        $this->whereBoolean('is_active', $value);
    }

    public function estimatedDurationFrom(int|string $value): void
    {
        $this->wherePositiveIntegerComparison('estimated_duration_minutes', '>=', $value);
    }

    public function estimatedDurationTo(int|string $value): void
    {
        $this->wherePositiveIntegerComparison('estimated_duration_minutes', '<=', $value);
    }

    public function createdFrom(string $value): void
    {
        $this->whereDateFrom('created_at', $value);
    }

    public function createdTo(string $value): void
    {
        $this->whereDateTo('created_at', $value);
    }
}
