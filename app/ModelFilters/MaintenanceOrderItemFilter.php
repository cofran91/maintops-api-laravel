<?php

namespace App\ModelFilters;

use App\Enums\MaintenanceOrderItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class MaintenanceOrderItemFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'maintenance_order_id',
        'maintenance_task_id',
        'maintenance_plan_id',
        'without_plan',
        'status',
        'pending_owner_approval_from',
        'pending_owner_approval_to',
        'scheduled_from',
        'scheduled_to',
        'started_from',
        'started_to',
        'finished_from',
        'finished_to',
        'rejected_from',
        'rejected_to',
        'cancelled_from',
        'cancelled_to',
        'created_from',
        'created_to',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->whereHas('maintenanceTask', function (Builder $query) use ($value): void {
                    $query
                        ->where('name', 'like', '%'.$value.'%')
                        ->orWhere('code', 'like', '%'.Str::upper($value).'%')
                        ->orWhere('description', 'like', '%'.$value.'%');
                })
                ->orWhereHas('maintenancePlan', function (Builder $query) use ($value): void {
                    $query
                        ->where('name', 'like', '%'.$value.'%')
                        ->orWhere('code', 'like', '%'.Str::upper($value).'%');
                })
                ->orWhereHas('maintenanceOrder.vehicle', function (Builder $query) use ($value): void {
                    $query->where('license_plate', 'like', '%'.Str::upper($value).'%');
                });
        });
    }

    public function maintenanceOrderId(int|string $value): void
    {
        $this->whereInteger('maintenance_order_id', $value);
    }

    public function maintenanceTaskId(int|string $value): void
    {
        $this->whereInteger('maintenance_task_id', $value);
    }

    public function maintenancePlanId(int|string $value): void
    {
        $this->whereNullableInteger('maintenance_plan_id', $value);
    }

    public function withoutPlan(bool|int|string $value): void
    {
        $this->whereBooleanNull('maintenance_plan_id', $value);
    }

    public function status(string $value): void
    {
        if (! in_array($value, MaintenanceOrderItemStatus::values(), true)) {
            return;
        }

        $this->where('status', $value);
    }

    public function pendingOwnerApprovalFrom(string $value): void
    {
        $this->whereDateFrom('pending_owner_approval_at', $value);
    }

    public function pendingOwnerApprovalTo(string $value): void
    {
        $this->whereDateTo('pending_owner_approval_at', $value);
    }

    public function scheduledFrom(string $value): void
    {
        $this->whereDateFrom('scheduled_at', $value);
    }

    public function scheduledTo(string $value): void
    {
        $this->whereDateTo('scheduled_at', $value);
    }

    public function startedFrom(string $value): void
    {
        $this->whereDateFrom('started_at', $value);
    }

    public function startedTo(string $value): void
    {
        $this->whereDateTo('started_at', $value);
    }

    public function finishedFrom(string $value): void
    {
        $this->whereDateFrom('finished_at', $value);
    }

    public function finishedTo(string $value): void
    {
        $this->whereDateTo('finished_at', $value);
    }

    public function rejectedFrom(string $value): void
    {
        $this->whereDateFrom('rejected_at', $value);
    }

    public function rejectedTo(string $value): void
    {
        $this->whereDateTo('rejected_at', $value);
    }

    public function cancelledFrom(string $value): void
    {
        $this->whereDateFrom('cancelled_at', $value);
    }

    public function cancelledTo(string $value): void
    {
        $this->whereDateTo('cancelled_at', $value);
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
