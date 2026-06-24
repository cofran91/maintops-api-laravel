<?php

namespace App\Http\Resources\Api\V1\Vehicles;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn (): array => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
                'is_active' => (bool) $this->owner->is_active,
                'phone' => $this->owner->phone,
                'document_number' => $this->owner->document_number,
                'address' => $this->owner->address,
            ]),
            'license_plate' => $this->license_plate,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'odometer_km' => $this->odometer_km,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
