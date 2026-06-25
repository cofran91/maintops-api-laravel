<?php

namespace Database\Seeders\PortfolioDemo;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->users() as $attributes) {
            $role = $attributes['role'];

            unset($attributes['role']);

            $user = User::query()->make(array_merge($attributes, [
                'password' => Hash::make('password'),
                'is_active' => true,
            ]));

            $user->email_verified_at = now();
            $user->save();

            $user->assignRole($role);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function users(): array
    {
        return [
            [
                'name' => 'Demo Operations Admin',
                'email' => 'admin.demo@maint.test',
                'role' => SystemRole::Admin->value,
                'phone' => '+57 300 100 0001',
                'document_number' => 'DEMO-ADMIN-001',
            ],
            [
                'name' => 'North Workshop Manager',
                'email' => 'manager.north@maint.test',
                'role' => SystemRole::WorkshopManager->value,
                'phone' => '+57 300 200 0001',
                'document_number' => 'DEMO-MANAGER-001',
            ],
            [
                'name' => 'South Workshop Manager',
                'email' => 'manager.south@maint.test',
                'role' => SystemRole::WorkshopManager->value,
                'phone' => '+57 300 200 0002',
                'document_number' => 'DEMO-MANAGER-002',
            ],
            [
                'name' => 'Engine Technician',
                'email' => 'technician.engine@maint.test',
                'role' => SystemRole::Technician->value,
                'phone' => '+57 300 300 0001',
                'document_number' => 'DEMO-TECH-001',
            ],
            [
                'name' => 'Brake Technician',
                'email' => 'technician.brakes@maint.test',
                'role' => SystemRole::Technician->value,
                'phone' => '+57 300 300 0002',
                'document_number' => 'DEMO-TECH-002',
            ],
            [
                'name' => 'Electrical Technician',
                'email' => 'technician.electrical@maint.test',
                'role' => SystemRole::Technician->value,
                'phone' => '+57 300 300 0003',
                'document_number' => 'DEMO-TECH-003',
            ],
            [
                'name' => 'Suspension Technician',
                'email' => 'technician.suspension@maint.test',
                'role' => SystemRole::Technician->value,
                'phone' => '+57 300 300 0004',
                'document_number' => 'DEMO-TECH-004',
            ],
            [
                'name' => 'North Service Advisor',
                'email' => 'advisor.north@maint.test',
                'role' => SystemRole::Advisor->value,
                'phone' => '+57 300 350 0001',
                'document_number' => 'DEMO-ADVISOR-001',
            ],
            [
                'name' => 'South Service Advisor',
                'email' => 'advisor.south@maint.test',
                'role' => SystemRole::Advisor->value,
                'phone' => '+57 300 350 0002',
                'document_number' => 'DEMO-ADVISOR-002',
            ],
        ];
    }
}
