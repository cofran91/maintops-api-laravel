<?php

namespace Tests\Feature\Api\Workshops;

use App\Enums\SystemRole;
use App\Models\Audit;
use App\Models\Workshop;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Workshops\Concerns\InteractsWithWorkshops;
use Tests\TestCase;

class WorkshopAuditTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_workshop_creation_with_vehicle_systems_is_recorded_as_one_business_audit(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'workshop.creator.audit@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.audit.manager@example.com']);
        $vehicleSystems = $this->vehicleSystems(2);
        Audit::query()->delete();

        $createdId = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/workshops', $this->workshopPayloadFor($manager, $vehicleSystems))
            ->assertCreated()
            ->json('data.id');

        $audit = Audit::query()
            ->where('event', 'workshop created')
            ->where('auditable_type', (new Workshop)->getMorphClass())
            ->where('auditable_id', $createdId)
            ->firstOrFail();

        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame('NORTH-WORKSHOP', $audit->new_values['attributes']['code']);
        $this->assertSame(
            array_map(static fn ($vehicleSystem): int => $vehicleSystem->id, $vehicleSystems),
            $audit->new_values['vehicle_system_ids'],
        );
        $this->assertSame(
            1,
            Audit::query()
                ->where('auditable_type', (new Workshop)->getMorphClass())
                ->where('auditable_id', $createdId)
                ->count(),
        );
    }

    public function test_workshop_update_records_old_and_new_snapshots_with_vehicle_systems(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, ['email' => 'workshop.updater.audit@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'workshop.update.audit.manager@example.com']);
        [$engine, $brakes, $electrical] = $this->vehicleSystems(3);
        $workshop = $this->workshopFor($manager, [$engine, $brakes], [
            'name' => 'Audited Workshop',
            'code' => 'AUDITED-WORKSHOP',
        ]);
        Audit::query()->delete();

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->patchJson('/api/v1/workshops/'.$workshop->id, $this->workshopUpdatePayloadFor($workshop, [
                'name' => 'Audited Updated Workshop',
                'vehicle_system_ids' => [$electrical->id],
            ]))
            ->assertOk();

        $audit = Audit::query()
            ->where('event', 'workshop updated')
            ->where('auditable_type', $workshop->getMorphClass())
            ->where('auditable_id', $workshop->id)
            ->firstOrFail();

        $this->assertSame('Audited Workshop', $audit->old_values['attributes']['name']);
        $this->assertSame('Audited Updated Workshop', $audit->new_values['attributes']['name']);
        $this->assertSame([$engine->id, $brakes->id], $audit->old_values['vehicle_system_ids']);
        $this->assertSame([$electrical->id], $audit->new_values['vehicle_system_ids']);
        $this->assertSame(
            1,
            Audit::query()
                ->where('auditable_type', $workshop->getMorphClass())
                ->where('auditable_id', $workshop->id)
                ->count(),
        );
    }
}
