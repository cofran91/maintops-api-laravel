<?php

namespace Tests\Feature\Api\Vehicles\Concerns;

use App\Models\Owner;
use App\Models\Vehicle;

trait InteractsWithVehicles
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function vehiclePayloadFor(Owner $owner, array $attributes = []): array
    {
        return array_merge([
            'owner_id' => $owner->id,
            'license_plate' => 'abc123',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'color' => 'White',
            'odometer_km' => 15200,
        ], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicleFor(Owner $owner, array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'owner_id' => $owner->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function vehicleUpdatePayloadFor(Vehicle $vehicle, array $attributes = []): array
    {
        return array_merge([
            'owner_id' => $vehicle->owner_id,
            'license_plate' => $vehicle->license_plate,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'odometer_km' => $vehicle->odometer_km,
        ], $attributes);
    }
}
