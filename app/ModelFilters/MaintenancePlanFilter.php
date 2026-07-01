<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class MaintenancePlanFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'name',
        'code',
        'is_active',
        'task_id',
        'recommended_interval_days_from',
        'recommended_interval_days_to',
        'recommended_interval_km_from',
        'recommended_interval_km_to',
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
                ->orWhereHas('tasks', function (Builder $query) use ($value): void {
                    $query
                        ->where('maintenance_tasks.name', 'like', '%'.$value.'%')
                        ->orWhere('maintenance_tasks.code', 'like', '%'.Str::upper($value).'%')
                        ->orWhere('maintenance_tasks.description', 'like', '%'.$value.'%');
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

    public function isActive(bool|int|string $value): void
    {
        $this->whereBoolean('is_active', $value);
    }

    public function taskId(int|string $value): void
    {
        $taskId = $this->positiveInteger($value);

        if ($taskId === null) {
            return;
        }

        $this->whereHas('tasks', function (Builder $query) use ($taskId): void {
            $query->where('maintenance_tasks.id', $taskId);
        });
    }

    public function recommendedIntervalDaysFrom(int|string $value): void
    {
        $this->wherePositiveIntegerComparison('recommended_interval_days', '>=', $value);
    }

    public function recommendedIntervalDaysTo(int|string $value): void
    {
        $this->wherePositiveIntegerComparison('recommended_interval_days', '<=', $value);
    }

    public function recommendedIntervalKmFrom(int|string $value): void
    {
        $this->wherePositiveIntegerComparison('recommended_interval_km', '>=', $value);
    }

    public function recommendedIntervalKmTo(int|string $value): void
    {
        $this->wherePositiveIntegerComparison('recommended_interval_km', '<=', $value);
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
