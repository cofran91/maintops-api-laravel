<?php

namespace App\Http\Resources\Api\V1\Workshops;

use App\Http\Resources\Api\V1\Users\UserResource;
use App\Http\Resources\Api\V1\VehicleSystems\VehicleSystemResource;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workshop
 */
class WorkshopResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manager_user_id' => $this->manager_user_id,
            'manager' => $this->whenLoaded('manager', fn (): array => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
                'roles' => $this->manager->getRoleNames()->values()->all(),
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'weekly_schedule' => $this->weekly_schedule,
            'vehicle_system_ids' => $this->whenLoaded(
                'vehicleSystems',
                fn (): array => $this->vehicleSystems->pluck('id')->values()->all(),
            ),
            'vehicle_systems' => VehicleSystemResource::collection($this->whenLoaded('vehicleSystems')),
            'technician_user_ids' => $this->whenLoaded(
                'technicians',
                fn (): array => $this->technicians->pluck('id')->values()->all(),
            ),
            'technicians' => UserResource::collection($this->whenLoaded('technicians')),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
