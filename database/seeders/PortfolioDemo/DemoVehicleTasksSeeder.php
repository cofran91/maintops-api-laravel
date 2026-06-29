<?php

namespace Database\Seeders\PortfolioDemo;

use App\Enums\MaintenanceTaskStatus;
use App\Enums\VehicleSystemCode;
use App\Models\MaintenanceTask;
use App\Models\Vehicle;
use App\Models\VehicleSystem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoVehicleTasksSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $vehicleIdsByLicensePlate = Vehicle::query()->pluck('id', 'license_plate');
        $vehicleSystemIdsByCode = VehicleSystem::query()->pluck('id', 'code');

        foreach ($this->tasks() as $attributes) {
            $licensePlate = $attributes['license_plate'];
            $vehicleSystemCode = $attributes['vehicle_system_code'];

            unset($attributes['license_plate'], $attributes['vehicle_system_code']);

            $task = MaintenanceTask::withTrashed()->updateOrCreate(
                ['code' => $attributes['code']],
                array_merge($attributes, [
                    'vehicle_id' => $vehicleIdsByLicensePlate[$licensePlate],
                    'vehicle_system_id' => $vehicleSystemIdsByCode[$vehicleSystemCode],
                    'is_active' => true,
                ]),
            );

            if ($task->trashed()) {
                $task->restore();
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tasks(): array
    {
        return [
            [
                'license_plate' => 'DEMO101',
                'vehicle_system_code' => VehicleSystemCode::Engine->value,
                'code' => 'DEMO-DIRECT-DEMO101-ENGINE-NOISE',
                'name' => 'Customer Reported Engine Noise',
                'description' => 'Owner reported a rattling noise when accelerating from low speed.',
                'estimated_duration_minutes' => 80,
                'status' => MaintenanceTaskStatus::Created->value,
            ],
            [
                'license_plate' => 'DEMO201',
                'vehicle_system_code' => VehicleSystemCode::Brakes->value,
                'code' => 'DEMO-DIRECT-DEMO201-BRAKE-PEDAL',
                'name' => 'Soft Brake Pedal Diagnosis',
                'description' => 'Advisor registered a soft brake pedal reported during city driving.',
                'estimated_duration_minutes' => 70,
                'status' => MaintenanceTaskStatus::Created->value,
            ],
            [
                'license_plate' => 'DEMO401',
                'vehicle_system_code' => VehicleSystemCode::Brakes->value,
                'code' => 'DEMO-DIRECT-DEMO401-BRAKE-VIBRATION',
                'name' => 'Brake Vibration Check',
                'description' => 'Owner reported vibration under moderate braking.',
                'estimated_duration_minutes' => 60,
                'status' => MaintenanceTaskStatus::Created->value,
            ],
            [
                'license_plate' => 'DEMO501',
                'vehicle_system_code' => VehicleSystemCode::Electrical->value,
                'code' => 'DEMO-DIRECT-DEMO501-ELECTRICAL-INTERMITTENT',
                'name' => 'Intermittent Electrical Failure',
                'description' => 'Dashboard warning lights appear intermittently after ignition.',
                'estimated_duration_minutes' => 75,
                'status' => MaintenanceTaskStatus::Scheduled->value,
            ],
            [
                'license_plate' => 'DEMO601',
                'vehicle_system_code' => VehicleSystemCode::Suspension->value,
                'code' => 'DEMO-DIRECT-DEMO601-SUSPENSION-NOISE',
                'name' => 'Rear Suspension Noise',
                'description' => 'Owner reported rear suspension noise over uneven roads.',
                'estimated_duration_minutes' => 70,
                'status' => MaintenanceTaskStatus::Completed->value,
            ],
            [
                'license_plate' => 'DEMO801',
                'vehicle_system_code' => VehicleSystemCode::Bodywork->value,
                'code' => 'DEMO-DIRECT-DEMO801-BODYWORK-DAMAGE',
                'name' => 'Bodywork Damage Review',
                'description' => 'Visible bodywork damage was reviewed before the customer cancelled service.',
                'estimated_duration_minutes' => 55,
                'status' => MaintenanceTaskStatus::Cancelled->value,
            ],
        ];
    }
}
