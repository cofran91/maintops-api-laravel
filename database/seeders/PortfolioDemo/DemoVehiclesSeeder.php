<?php

namespace Database\Seeders\PortfolioDemo;

use App\Models\Owner;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoVehiclesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $ownerIdsByEmail = Owner::query()->pluck('id', 'email');

        foreach ($this->vehicles() as $attributes) {
            $ownerEmail = $attributes['owner_email'];

            unset($attributes['owner_email']);

            $vehicle = Vehicle::withTrashed()->updateOrCreate(
                ['license_plate' => $attributes['license_plate']],
                array_merge($attributes, [
                    'owner_id' => $ownerIdsByEmail[$ownerEmail],
                ]),
            );

            if ($vehicle->trashed()) {
                $vehicle->restore();
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vehicles(): array
    {
        return [
            [
                'owner_email' => 'owner.sofia@maint.test',
                'license_plate' => 'DEMO101',
                'brand' => 'Toyota',
                'model' => 'Hilux',
                'year' => 2021,
                'color' => 'White',
                'odometer_km' => 45600,
            ],
            [
                'owner_email' => 'owner.daniel@maint.test',
                'license_plate' => 'DEMO201',
                'brand' => 'Mazda',
                'model' => 'CX-5',
                'year' => 2020,
                'color' => 'Gray',
                'odometer_km' => 38200,
            ],
            [
                'owner_email' => 'owner.laura@maint.test',
                'license_plate' => 'DEMO301',
                'brand' => 'Chevrolet',
                'model' => 'Tracker',
                'year' => 2019,
                'color' => 'Blue',
                'odometer_km' => 74300,
            ],
            [
                'owner_email' => 'owner.mateo@maint.test',
                'license_plate' => 'DEMO401',
                'brand' => 'Renault',
                'model' => 'Duster',
                'year' => 2022,
                'color' => 'Black',
                'odometer_km' => 21800,
            ],
            [
                'owner_email' => 'owner.valentina@maint.test',
                'license_plate' => 'DEMO501',
                'brand' => 'Nissan',
                'model' => 'Frontier',
                'year' => 2021,
                'color' => 'Silver',
                'odometer_km' => 62900,
            ],
            [
                'owner_email' => 'owner.andres@maint.test',
                'license_plate' => 'DEMO601',
                'brand' => 'Kia',
                'model' => 'Sportage',
                'year' => 2023,
                'color' => 'Red',
                'odometer_km' => 12900,
            ],
            [
                'owner_email' => 'owner.camila@maint.test',
                'license_plate' => 'DEMO701',
                'brand' => 'Ford',
                'model' => 'Ranger',
                'year' => 2020,
                'color' => 'Green',
                'odometer_km' => 88500,
            ],
            [
                'owner_email' => 'owner.nicolas@maint.test',
                'license_plate' => 'DEMO801',
                'brand' => 'Hyundai',
                'model' => 'Tucson',
                'year' => 2024,
                'color' => 'Graphite',
                'odometer_km' => 8200,
            ],
        ];
    }
}
