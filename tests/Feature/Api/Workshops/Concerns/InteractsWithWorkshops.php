<?php

namespace Tests\Feature\Api\Workshops\Concerns;

use App\Models\User;
use App\Models\VehicleSystem;
use App\Models\Workshop;

trait InteractsWithWorkshops
{
    /**
     * @param  array<int, VehicleSystem>  $vehicleSystems
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function workshopPayloadFor(User $manager, array $vehicleSystems, array $attributes = []): array
    {
        return array_merge([
            'manager_user_id' => $manager->id,
            'name' => 'North Workshop',
            'code' => 'north-workshop',
            'address' => '10 Main Street',
            'city' => 'Bogota',
            'phone' => '+57 300 123 4567',
            'email' => 'NORTH.WORKSHOP@EXAMPLE.COM',
            'weekly_schedule' => [
                'Monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'Tuesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
            ],
            'vehicle_system_ids' => array_map(
                static fn (VehicleSystem $vehicleSystem): int => $vehicleSystem->id,
                $vehicleSystems,
            ),
            'is_active' => true,
        ], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function workshopUpdatePayloadFor(Workshop $workshop, array $attributes = []): array
    {
        return array_merge([
            'manager_user_id' => $workshop->manager_user_id,
            'name' => $workshop->name,
            'code' => $workshop->code,
            'address' => $workshop->address,
            'city' => $workshop->city,
            'phone' => $workshop->phone,
            'email' => $workshop->email,
            'weekly_schedule' => $workshop->weekly_schedule,
            'vehicle_system_ids' => $workshop->vehicleSystems()->pluck('vehicle_systems.id')->all(),
            'is_active' => (bool) $workshop->is_active,
        ], $attributes);
    }

    /**
     * @param  array<int, VehicleSystem>  $vehicleSystems
     * @param  array<string, mixed>  $attributes
     */
    private function workshopFor(User $manager, array $vehicleSystems, array $attributes = []): Workshop
    {
        $workshop = Workshop::factory()->create(array_merge([
            'manager_user_id' => $manager->id,
            'name' => 'Workshop '.uniqid(),
            'code' => 'WORKSHOP-'.strtoupper(uniqid()),
        ], $attributes));

        $workshop->vehicleSystems()->sync(array_map(
            static fn (VehicleSystem $vehicleSystem): int => $vehicleSystem->id,
            $vehicleSystems,
        ));

        return $workshop->refresh();
    }

    /**
     * @return array<int, VehicleSystem>
     */
    private function vehicleSystems(int $count = 2): array
    {
        return VehicleSystem::query()
            ->orderBy('id')
            ->take($count)
            ->get()
            ->all();
    }
}
