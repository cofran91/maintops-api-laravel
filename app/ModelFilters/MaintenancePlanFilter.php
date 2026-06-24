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
        $isActive = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($isActive === null) {
            return;
        }

        $this->where('is_active', $isActive);
    }

    public function taskId(int|string $value): void
    {
        $taskId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($taskId === false) {
            return;
        }

        $this->whereHas('tasks', function (Builder $query) use ($taskId): void {
            $query->where('maintenance_tasks.id', $taskId);
        });
    }

    public function recommendedIntervalDaysFrom(int|string $value): void
    {
        $days = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($days === false) {
            return;
        }

        $this->where('recommended_interval_days', '>=', $days);
    }

    public function recommendedIntervalDaysTo(int|string $value): void
    {
        $days = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($days === false) {
            return;
        }

        $this->where('recommended_interval_days', '<=', $days);
    }

    public function recommendedIntervalKmFrom(int|string $value): void
    {
        $kilometers = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($kilometers === false) {
            return;
        }

        $this->where('recommended_interval_km', '>=', $kilometers);
    }

    public function recommendedIntervalKmTo(int|string $value): void
    {
        $kilometers = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($kilometers === false) {
            return;
        }

        $this->where('recommended_interval_km', '<=', $kilometers);
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
