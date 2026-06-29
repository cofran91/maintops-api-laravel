<?php

namespace Database\Seeders\PortfolioDemo;

use App\Enums\MaintenanceTaskStatus;
use App\Enums\VehicleSystemCode;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\VehicleSystem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoMaintenancePlansSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $vehicleSystemIdsByCode = VehicleSystem::query()
            ->pluck('id', 'code');

        foreach ($this->plans() as $attributes) {
            $tasks = $attributes['tasks'];

            unset($attributes['tasks']);

            $plan = MaintenancePlan::withTrashed()->updateOrCreate(
                ['code' => $attributes['code']],
                array_merge($attributes, [
                    'is_active' => true,
                ]),
            );

            if ($plan->trashed()) {
                $plan->restore();
            }

            $taskIds = [];

            foreach ($tasks as $taskAttributes) {
                $systemCode = $taskAttributes['vehicle_system_code'];

                unset($taskAttributes['vehicle_system_code']);

                $task = MaintenanceTask::withTrashed()->updateOrCreate(
                    ['code' => $taskAttributes['code']],
                    array_merge($taskAttributes, [
                        'vehicle_id' => null,
                        'vehicle_system_id' => $vehicleSystemIdsByCode[$systemCode],
                        'status' => MaintenanceTaskStatus::Created->value,
                        'is_active' => true,
                    ]),
                );

                if ($task->trashed()) {
                    $task->restore();
                }

                $taskIds[] = $task->id;
            }

            $plan->tasks()->sync($taskIds);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'code' => 'DEMO-PLAN-CORE-PREVENTIVE',
                'name' => 'Core Preventive Plan',
                'description' => 'Recurring preventive checks for high-usage vehicles.',
                'recommended_interval_days' => 90,
                'recommended_interval_km' => 10000,
                'tasks' => [
                    [
                        'vehicle_system_code' => VehicleSystemCode::Engine->value,
                        'code' => 'DEMO-TASK-ENGINE-INSPECTION',
                        'name' => 'Engine General Inspection',
                        'description' => 'Check leaks, abnormal noises, belts, hoses, and visible engine components.',
                        'estimated_duration_minutes' => 90,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Brakes->value,
                        'code' => 'DEMO-TASK-BRAKES-INSPECTION',
                        'name' => 'Brake System Inspection',
                        'description' => 'Validate pad wear, discs, drums, hydraulic lines, and brake fluid level.',
                        'estimated_duration_minutes' => 75,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Electrical->value,
                        'code' => 'DEMO-TASK-ELECTRICAL-DIAGNOSTIC',
                        'name' => 'Basic Electrical Diagnostic',
                        'description' => 'Review battery, alternator, lights, fuses, and main electrical connections.',
                        'estimated_duration_minutes' => 60,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Cooling->value,
                        'code' => 'DEMO-TASK-COOLING-CHECK',
                        'name' => 'Cooling System Check',
                        'description' => 'Check coolant level, hoses, radiator, fan behavior, and visible leaks.',
                        'estimated_duration_minutes' => 45,
                    ],
                ],
            ],
            [
                'code' => 'DEMO-PLAN-POWERTRAIN-FLUIDS',
                'name' => 'Powertrain And Fluids Plan',
                'description' => 'General activities for transmission, fuel, and hydraulic systems.',
                'recommended_interval_days' => 120,
                'recommended_interval_km' => 15000,
                'tasks' => [
                    [
                        'vehicle_system_code' => VehicleSystemCode::Transmission->value,
                        'code' => 'DEMO-TASK-TRANSMISSION-CHECK',
                        'name' => 'Transmission Check',
                        'description' => 'Inspect shifting behavior, clutch condition, fluid level, and possible leaks.',
                        'estimated_duration_minutes' => 80,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Fuel->value,
                        'code' => 'DEMO-TASK-FUEL-SYSTEM-CHECK',
                        'name' => 'Fuel System Inspection',
                        'description' => 'Review fuel lines, filter, pump behavior, and evidence of fuel leaks.',
                        'estimated_duration_minutes' => 50,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Hydraulic->value,
                        'code' => 'DEMO-TASK-HYDRAULIC-CHECK',
                        'name' => 'Hydraulic System Review',
                        'description' => 'Verify fluid, pressure behavior, hoses, fittings, and visible leaks.',
                        'estimated_duration_minutes' => 65,
                    ],
                ],
            ],
            [
                'code' => 'DEMO-PLAN-CHASSIS-SAFETY',
                'name' => 'Chassis And Safety Plan',
                'description' => 'Safety-oriented activities for suspension, tires, and bodywork.',
                'recommended_interval_days' => 180,
                'recommended_interval_km' => 20000,
                'tasks' => [
                    [
                        'vehicle_system_code' => VehicleSystemCode::Suspension->value,
                        'code' => 'DEMO-TASK-SUSPENSION-INSPECTION',
                        'name' => 'Suspension Inspection',
                        'description' => 'Review shock absorbers, bushings, joints, mounts, and abnormal play.',
                        'estimated_duration_minutes' => 70,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Tires->value,
                        'code' => 'DEMO-TASK-TIRES-ROTATION-CHECK',
                        'name' => 'Tire Rotation And Check',
                        'description' => 'Check pressure, wear pattern, visual alignment, and rotation needs.',
                        'estimated_duration_minutes' => 45,
                    ],
                    [
                        'vehicle_system_code' => VehicleSystemCode::Bodywork->value,
                        'code' => 'DEMO-TASK-BODYWORK-INSPECTION',
                        'name' => 'Bodywork Inspection',
                        'description' => 'Check panels, supports, closures, hinges, and visible corrosion points.',
                        'estimated_duration_minutes' => 55,
                    ],
                ],
            ],
        ];
    }
}
