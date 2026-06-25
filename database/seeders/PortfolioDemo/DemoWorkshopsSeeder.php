<?php

namespace Database\Seeders\PortfolioDemo;

use App\Enums\VehicleSystemCode;
use App\Models\User;
use App\Models\VehicleSystem;
use App\Models\Workshop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoWorkshopsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userIdsByEmail = User::query()->pluck('id', 'email');
        $vehicleSystemIdsByCode = VehicleSystem::query()->pluck('id', 'code');

        foreach ($this->workshops() as $attributes) {
            $systemCodes = $attributes['vehicle_system_codes'];
            $managerEmail = $attributes['manager_email'];
            $technicianEmails = $attributes['technician_emails'];

            unset($attributes['vehicle_system_codes'], $attributes['manager_email'], $attributes['technician_emails']);

            $workshop = Workshop::query()->create(array_merge($attributes, [
                'manager_user_id' => $userIdsByEmail[$managerEmail],
                'is_active' => true,
            ]));

            $workshop->vehicleSystems()->attach(
                collect($systemCodes)
                    ->map(fn (string $code): int => $vehicleSystemIdsByCode[$code])
                    ->all(),
            );

            $this->assignTechnicians($workshop, $technicianEmails);
        }
    }

    /**
     * @param  array<int, string>  $technicianEmails
     */
    private function assignTechnicians(Workshop $workshop, array $technicianEmails): void
    {
        User::query()
            ->whereIn('email', $technicianEmails)
            ->update(['workshop_id' => $workshop->id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workshops(): array
    {
        return [
            [
                'manager_email' => 'manager.north@maint.test',
                'name' => 'North Maintenance Workshop',
                'code' => 'DEMO-WORKSHOP-NORTH',
                'address' => 'Calle 10 #20-30',
                'city' => 'Bogota',
                'phone' => '+57 300 500 0001',
                'email' => 'workshop.north@maint.test',
                'weekly_schedule' => [
                    'monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                    'tuesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                    'wednesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                    'thursday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                    'friday' => ['opens_at' => '08:00', 'closes_at' => '16:00'],
                ],
                'vehicle_system_codes' => [
                    VehicleSystemCode::Engine->value,
                    VehicleSystemCode::Brakes->value,
                    VehicleSystemCode::Electrical->value,
                    VehicleSystemCode::Cooling->value,
                    VehicleSystemCode::Transmission->value,
                ],
                'technician_emails' => [
                    'technician.engine@maint.test',
                    'technician.brakes@maint.test',
                ],
            ],
            [
                'manager_email' => 'manager.south@maint.test',
                'name' => 'South Maintenance Workshop',
                'code' => 'DEMO-WORKSHOP-SOUTH',
                'address' => 'Carrera 45 #12-80',
                'city' => 'Medellin',
                'phone' => '+57 300 500 0002',
                'email' => 'workshop.south@maint.test',
                'weekly_schedule' => [
                    'monday' => ['opens_at' => '07:30', 'closes_at' => '16:30'],
                    'tuesday' => ['opens_at' => '07:30', 'closes_at' => '16:30'],
                    'wednesday' => ['opens_at' => '07:30', 'closes_at' => '16:30'],
                    'thursday' => ['opens_at' => '07:30', 'closes_at' => '16:30'],
                    'saturday' => ['opens_at' => '08:00', 'closes_at' => '12:00'],
                ],
                'vehicle_system_codes' => [
                    VehicleSystemCode::Fuel->value,
                    VehicleSystemCode::Hydraulic->value,
                    VehicleSystemCode::Suspension->value,
                    VehicleSystemCode::Tires->value,
                    VehicleSystemCode::Bodywork->value,
                ],
                'technician_emails' => [
                    'technician.electrical@maint.test',
                    'technician.suspension@maint.test',
                ],
            ],
        ];
    }
}
