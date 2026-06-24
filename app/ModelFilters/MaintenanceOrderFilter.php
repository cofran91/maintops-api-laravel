<?php

namespace App\ModelFilters;

use App\Enums\MaintenanceOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class MaintenanceOrderFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'vehicle_id',
        'owner_id',
        'advisor_id',
        'workshop_id',
        'without_workshop',
        'technician_id',
        'without_technician',
        'status',
        'scheduled_from',
        'scheduled_to',
        'started_from',
        'started_to',
        'finished_from',
        'finished_to',
        'delivered_from',
        'delivered_to',
        'cancelled_from',
        'cancelled_to',
        'created_from',
        'created_to',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->whereHas('vehicle', function (Builder $query) use ($value): void {
                    $query
                        ->where('license_plate', 'like', '%'.Str::upper($value).'%')
                        ->orWhere('brand', 'like', '%'.$value.'%')
                        ->orWhere('model', 'like', '%'.$value.'%');
                })
                ->orWhereHas('vehicle.owner', fn (Builder $query) => $this->whereUserLike($query, $value))
                ->orWhereHas('advisor', fn (Builder $query) => $this->whereUserLike($query, $value))
                ->orWhereHas('technician', fn (Builder $query) => $this->whereUserLike($query, $value))
                ->orWhereHas('workshop', function (Builder $query) use ($value): void {
                    $query
                        ->where('name', 'like', '%'.$value.'%')
                        ->orWhere('code', 'like', '%'.Str::upper($value).'%')
                        ->orWhere('city', 'like', '%'.$value.'%');
                });
        });
    }

    public function vehicleId(int|string $value): void
    {
        $this->whereInteger('vehicle_id', $value);
    }

    public function ownerId(int|string $value): void
    {
        $ownerId = $this->positiveInteger($value);

        if ($ownerId === null) {
            return;
        }

        $this->whereHas('vehicle', function (Builder $query) use ($ownerId): void {
            $query->where('owner_id', $ownerId);
        });
    }

    public function advisorId(int|string $value): void
    {
        $this->whereInteger('advisor_id', $value);
    }

    public function workshopId(int|string $value): void
    {
        $this->whereNullableInteger('workshop_id', $value);
    }

    public function withoutWorkshop(bool|int|string $value): void
    {
        $this->whereBooleanNull('workshop_id', $value);
    }

    public function technicianId(int|string $value): void
    {
        $this->whereNullableInteger('technician_id', $value);
    }

    public function withoutTechnician(bool|int|string $value): void
    {
        $this->whereBooleanNull('technician_id', $value);
    }

    public function status(string $value): void
    {
        if (! in_array($value, MaintenanceOrderStatus::values(), true)) {
            return;
        }

        $this->where('status', $value);
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

    public function deliveredFrom(string $value): void
    {
        $this->whereDateFrom('delivered_at', $value);
    }

    public function deliveredTo(string $value): void
    {
        $this->whereDateTo('delivered_at', $value);
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

    private function whereUserLike(Builder $query, string $value): void
    {
        $query
            ->where('name', 'like', '%'.$value.'%')
            ->orWhere('email', 'like', '%'.$value.'%')
            ->orWhere('document_number', 'like', '%'.$value.'%');
    }

    private function whereInteger(string $field, int|string $value): void
    {
        $integer = $this->positiveInteger($value);

        if ($integer === null) {
            return;
        }

        $this->where($field, $integer);
    }

    private function whereNullableInteger(string $field, int|string $value): void
    {
        $normalizedValue = strtolower(trim((string) $value));

        if (in_array($normalizedValue, ['null', 'none', 'unassigned'], true)) {
            $this->whereNull($field);

            return;
        }

        $this->whereInteger($field, $value);
    }

    private function whereBooleanNull(string $field, bool|int|string $value): void
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($enabled !== true) {
            return;
        }

        $this->whereNull($field);
    }

    private function whereDateFrom(string $field, string $value): void
    {
        $date = $this->dateFilterValue($value);

        if ($date === null) {
            return;
        }

        $this->where($field, '>=', $date->startOfDay());
    }

    private function whereDateTo(string $field, string $value): void
    {
        $date = $this->dateFilterValue($value);

        if ($date === null) {
            return;
        }

        $this->where($field, '<=', $date->endOfDay());
    }

    private function positiveInteger(int|string $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : $integer;
    }
}
