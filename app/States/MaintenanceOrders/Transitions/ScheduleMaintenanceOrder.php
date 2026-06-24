<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

class ScheduleMaintenanceOrder extends UpdateMaintenanceOrderStatus
{
    public function handle(): MaintenanceOrder
    {
        $attributes = $this->attributes;

        if (($attributes['scheduled_at'] ?? $this->model->scheduled_at) === null) {
            $attributes['scheduled_at'] = now();
        }

        $scheduledAt = Carbon::parse($attributes['scheduled_at']);
        $attributes['scheduled_at'] = $scheduledAt;

        $items = $this->model->items()
            ->with('maintenanceTask')
            ->where('status', MaintenanceOrderItemStatus::Scheduled->value)
            ->whereNull('scheduled_at')
            ->get();

        $items->each(function ($item) use ($scheduledAt): void {
            $plannedDuration = $item->planned_duration_minutes
                ?? $item->maintenanceTask?->estimated_duration_minutes;

            $item->update([
                'scheduled_at' => $scheduledAt,
                'planned_duration_minutes' => $plannedDuration,
                'scheduled_ends_at' => $plannedDuration === null
                    ? null
                    : $scheduledAt->copy()->addMinutes($plannedDuration),
            ]);
        });

        $this->model->fill($attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }
}
