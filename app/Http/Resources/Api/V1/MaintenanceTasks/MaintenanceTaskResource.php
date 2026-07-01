<?php

namespace App\Http\Resources\Api\V1\MaintenanceTasks;

use App\Http\Resources\Api\V1\Vehicles\VehicleResource;
use App\Http\Resources\Api\V1\VehicleSystems\VehicleSystemResource;
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
            'vehicle' => $this->whenLoaded('vehicle', fn () => VehicleResource::make($this->vehicle)),
            'vehicle_system_id' => $this->vehicle_system_id,
            'vehicle_system' => $this->whenLoaded('vehicleSystem', fn () => VehicleSystemResource::make($this->vehicleSystem)),
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
