<?php

namespace Tests\Feature\Api\Vehicles;

use App\Models\Owner;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerVehicleRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_has_many_vehicles(): void
    {
        $owner = Owner::factory()->create();
        $vehicles = Vehicle::factory()
            ->count(2)
            ->create(['owner_id' => $owner->id]);

        $this->assertEquals(
            $vehicles->pluck('id')->all(),
            $owner->vehicles()->pluck('id')->all(),
        );
    }
}
