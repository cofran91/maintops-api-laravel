<?php

namespace App\Http\Resources\Api\V1\MaintenanceOrders;

use App\Http\Resources\Api\V1\MaintenancePlans\MaintenancePlanResource;
use App\Http\Resources\Api\V1\MaintenanceTasks\MaintenanceTaskResource;
use App\Models\MaintenanceOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceOrderItem
 */
class MaintenanceOrderItemResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_order_id' => $this->maintenance_order_id,
            'maintenance_order' => $this->whenLoaded(
                'maintenanceOrder',
                fn () => MaintenanceOrderResource::make($this->maintenanceOrder),
            ),
            'maintenance_task_id' => $this->maintenance_task_id,
            'maintenance_task' => $this->whenLoaded(
                'maintenanceTask',
                fn () => MaintenanceTaskResource::make($this->maintenanceTask),
            ),
            'maintenance_plan_id' => $this->maintenance_plan_id,
            'maintenance_plan' => $this->whenLoaded(
                'maintenancePlan',
                fn () => MaintenancePlanResource::make($this->maintenancePlan),
            ),
            'status' => $this->status?->getValue(),
            'odometer_km' => $this->odometer_km,
            'planned_duration_minutes' => $this->planned_duration_minutes,
            'pending_owner_approval_at' => $this->pending_owner_approval_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'scheduled_ends_at' => $this->scheduled_ends_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
