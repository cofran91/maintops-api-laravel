<?php

namespace Tests\Feature\Api\Workshops;

use App\Models\User;
use App\Models\VehicleSystem;
use App\Models\Workshop;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopVehicleSystemRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_workshop_belongs_to_many_vehicle_systems(): void
    {
        $workshop = Workshop::factory()->create();
        $vehicleSystems = VehicleSystem::query()->take(2)->get();

        $workshop->vehicleSystems()->sync($vehicleSystems->pluck('id')->all());

        $this->assertEquals(
            $vehicleSystems->pluck('id')->all(),
            $workshop->vehicleSystems()->pluck('vehicle_systems.id')->all(),
        );
    }

    public function test_user_has_managed_workshop(): void
    {
        $manager = User::factory()->create();
        $workshop = Workshop::factory()->create(['manager_user_id' => $manager->id]);

        $this->assertTrue($manager->managedWorkshop->is($workshop));
        $this->assertTrue($workshop->manager->is($manager));
    }

    public function test_workshop_has_technicians(): void
    {
        $workshop = Workshop::factory()->create();
        $technician = User::factory()->create(['workshop_id' => $workshop->id]);

        $this->assertTrue($technician->workshop->is($workshop));
        $this->assertTrue($workshop->technicians->first()->is($technician));
    }
}
