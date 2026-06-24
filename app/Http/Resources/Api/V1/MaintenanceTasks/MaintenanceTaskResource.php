<?php

namespace App\Http\Resources\Api\V1\MaintenanceTasks;

use App\Models\MaintenanceTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceTask
 */
class MaintenanceTaskResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => $this->whenLoaded('vehicle', function (): ?array {
                if ($this->vehicle === null) {
                    return null;
                }

                return [
                    'id' => $this->vehicle->id,
                    'owner_id' => $this->vehicle->owner_id,
                    'license_plate' => $this->vehicle->license_plate,
                    'brand' => $this->vehicle->brand,
                    'model' => $this->vehicle->model,
                    'year' => $this->vehicle->year,
                    'color' => $this->vehicle->color,
                    'odometer_km' => $this->vehicle->odometer_km,
                ];
            }),
            'vehicle_system_id' => $this->vehicle_system_id,
            'vehicle_system' => $this->whenLoaded('vehicleSystem', fn (): array => [
                'id' => $this->vehicleSystem->id,
                'code' => $this->vehicleSystem->code,
                'name' => $this->vehicleSystem->name,
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'status' => $this->status?->getValue(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
