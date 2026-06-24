<?php

namespace App\Http\Resources\Api\V1\MaintenanceOrders;

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
            'maintenance_order' => $this->whenLoaded('maintenanceOrder', function (): array {
                return [
                    'id' => $this->maintenanceOrder->id,
                    'vehicle_id' => $this->maintenanceOrder->vehicle_id,
                    'owner_id' => $this->maintenanceOrder->relationLoaded('vehicle')
                        ? $this->maintenanceOrder->vehicle?->owner_id
                        : null,
                    'advisor_id' => $this->maintenanceOrder->advisor_id,
                    'workshop_id' => $this->maintenanceOrder->workshop_id,
                    'technician_id' => $this->maintenanceOrder->technician_id,
                    'status' => $this->maintenanceOrder->status?->value,
                ];
            }),
            'maintenance_task_id' => $this->maintenance_task_id,
            'maintenance_task' => $this->whenLoaded('maintenanceTask', function (): array {
                return [
                    'id' => $this->maintenanceTask->id,
                    'vehicle_id' => $this->maintenanceTask->vehicle_id,
                    'vehicle_system_id' => $this->maintenanceTask->vehicle_system_id,
                    'vehicle_system' => $this->maintenanceTask->relationLoaded('vehicleSystem')
                        ? [
                            'id' => $this->maintenanceTask->vehicleSystem->id,
                            'code' => $this->maintenanceTask->vehicleSystem->code,
                            'name' => $this->maintenanceTask->vehicleSystem->name,
                        ]
                        : null,
                    'name' => $this->maintenanceTask->name,
                    'code' => $this->maintenanceTask->code,
                    'description' => $this->maintenanceTask->description,
                    'estimated_duration_minutes' => $this->maintenanceTask->estimated_duration_minutes,
                    'status' => $this->maintenanceTask->status?->value,
                    'is_active' => (bool) $this->maintenanceTask->is_active,
                ];
            }),
            'maintenance_plan_id' => $this->maintenance_plan_id,
            'maintenance_plan' => $this->whenLoaded('maintenancePlan', function (): ?array {
                if ($this->maintenancePlan === null) {
                    return null;
                }

                return [
                    'id' => $this->maintenancePlan->id,
                    'code' => $this->maintenancePlan->code,
                    'name' => $this->maintenancePlan->name,
                    'recommended_interval_days' => $this->maintenancePlan->recommended_interval_days,
                    'recommended_interval_km' => $this->maintenancePlan->recommended_interval_km,
                    'is_active' => (bool) $this->maintenancePlan->is_active,
                ];
            }),
            'status' => $this->status?->value,
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
