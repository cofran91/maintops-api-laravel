<?php

namespace App\Http\Resources\Api\V1\MaintenanceOrders;

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
            'vehicle' => $this->whenLoaded('vehicle', function (): array {
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
            'owner_id' => $this->vehicle?->owner_id,
            'owner' => $this->whenLoaded('vehicle', function (): ?array {
                if (! $this->vehicle->relationLoaded('owner') || $this->vehicle->owner === null) {
                    return null;
                }

                return $this->ownerSummary($this->vehicle->owner);
            }),
            'advisor_id' => $this->advisor_id,
            'advisor' => $this->whenLoaded('advisor', fn (): array => $this->userSummary($this->advisor)),
            'workshop_id' => $this->workshop_id,
            'workshop' => $this->whenLoaded('workshop', function (): ?array {
                if ($this->workshop === null) {
                    return null;
                }

                return [
                    'id' => $this->workshop->id,
                    'name' => $this->workshop->name,
                    'code' => $this->workshop->code,
                    'city' => $this->workshop->city,
                    'is_active' => (bool) $this->workshop->is_active,
                ];
            }),
            'technician_id' => $this->technician_id,
            'technician' => $this->whenLoaded('technician', function (): ?array {
                if ($this->technician === null) {
                    return null;
                }

                return $this->userSummary($this->technician);
            }),
            'status' => $this->status?->value,
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

    /**
     * @return array<string, mixed>
     */
    private function userSummary(mixed $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'phone' => $user->phone,
            'document_number' => $user->document_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerSummary(mixed $owner): array
    {
        return [
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => (bool) $owner->is_active,
            'phone' => $owner->phone,
            'document_number' => $owner->document_number,
        ];
    }
}
