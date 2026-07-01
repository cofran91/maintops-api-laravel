<?php

namespace App\Http\Resources\Api\V1\MaintenanceOrders;

use App\Http\Resources\Api\V1\Owners\OwnerResource;
use App\Http\Resources\Api\V1\Users\UserResource;
use App\Http\Resources\Api\V1\Vehicles\VehicleResource;
use App\Http\Resources\Api\V1\Workshops\WorkshopResource;
use App\Models\MaintenanceOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceOrder
 */
class MaintenanceOrderResource extends JsonResource
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
            'owner_id' => $this->vehicle?->owner_id,
            'owner' => $this->whenLoaded('vehicle', function () {
                if (! $this->vehicle->relationLoaded('owner') || $this->vehicle->owner === null) {
                    return null;
                }

                return OwnerResource::make($this->vehicle->owner);
            }),
            'advisor_id' => $this->advisor_id,
            'advisor' => $this->whenLoaded('advisor', fn () => UserResource::make($this->advisor)),
            'workshop_id' => $this->workshop_id,
            'workshop' => $this->whenLoaded('workshop', fn () => WorkshopResource::make($this->workshop)),
            'technician_id' => $this->technician_id,
            'technician' => $this->whenLoaded('technician', fn () => UserResource::make($this->technician)),
            'status' => $this->status?->getValue(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'item_ids' => $this->whenLoaded(
                'items',
                fn (): array => $this->items->pluck('id')->values()->all(),
            ),
            'items' => MaintenanceOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
