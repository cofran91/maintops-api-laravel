<?php

namespace Database\Seeders\PortfolioDemo;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PortfolioDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DemoUsersSeeder::class,
            DemoOwnersSeeder::class,
            DemoWorkshopsSeeder::class,
            DemoVehiclesSeeder::class,
            DemoMaintenancePlansSeeder::class,
            DemoVehicleTasksSeeder::class,
            DemoMaintenanceOrdersSeeder::class,
        ]);
    }
}
