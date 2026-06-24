<?php

namespace Database\Seeders;

use App\Enums\VehicleSystemCode;
use App\Models\VehicleSystem;
use Illuminate\Database\Seeder;

class VehicleSystemSeeder extends Seeder
{
    public function run(): void
    {
        foreach (VehicleSystemCode::cases() as $system) {
            VehicleSystem::query()->updateOrCreate(
                ['code' => $system->value],
                ['name' => $system->label()],
            );
        }
    }
}
