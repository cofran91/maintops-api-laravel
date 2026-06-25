<?php

namespace Tests\Feature\Api\Integrations;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class AnalyticsInitialSyncTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);

        config(['operations.analytics.service_key' => 'test-analytics-service-key']);
    }

    public function test_internal_service_can_fetch_paginated_item_snapshot_without_pii(): void
    {
        $owner = $this->ownerFor(['email' => 'initial-sync.owner@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'initial-sync.advisor@example.com']);
        $vehicle = $this->vehicleFor($owner);
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Scheduled->value,
        ]);
        $task = $this->maintenanceTaskFor([
            'vehicle_id' => $vehicle->id,
            'estimated_duration_minutes' => 80,
        ]);
        $item = $this->maintenanceOrderItemFor($order, $task, [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'planned_duration_minutes' => 80,
            'scheduled_at' => now()->addHour(),
            'scheduled_ends_at' => now()->addHours(2)->addMinutes(20),
        ]);

        $this->withHeaders([
            'X-Operations-Service-Key' => 'test-analytics-service-key',
        ])
            ->getJson('/api/v1/internal/analytics/initial-sync/maintenance-order-items?limit=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Analytics initial sync snapshot retrieved.')
            ->assertJsonPath('data.items.0.id', $item->id)
            ->assertJsonPath('data.items.0.maintenance_order_id', $order->id)
            ->assertJsonPath('data.items.0.planned_duration_minutes', 80)
            ->assertJsonPath('data.meta.resource', 'maintenance-order-items')
            ->assertJsonMissing(['email' => 'initial-sync.owner@example.com'])
            ->assertJsonMissing(['email' => 'initial-sync.advisor@example.com']);
    }

    public function test_internal_service_can_page_initial_sync_snapshots_by_cursor(): void
    {
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'initial-sync.manager@example.com']);
        $firstWorkshop = $this->workshopFor($manager, ['name' => 'Initial Sync One']);
        $secondWorkshop = $this->workshopFor(
            $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'initial-sync.manager.two@example.com']),
            ['name' => 'Initial Sync Two'],
        );

        $response = $this->withHeaders([
            'X-Operations-Service-Key' => 'test-analytics-service-key',
        ])
            ->getJson('/api/v1/internal/analytics/initial-sync/workshops?limit=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $firstWorkshop->id)
            ->assertJsonPath('data.meta.next_cursor', $firstWorkshop->id);

        $nextCursor = $response->json('data.meta.next_cursor');

        $this->withHeaders([
            'X-Operations-Service-Key' => 'test-analytics-service-key',
        ])
            ->getJson('/api/v1/internal/analytics/initial-sync/workshops?limit=1&cursor='.$nextCursor)
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $secondWorkshop->id);
    }

    public function test_initial_sync_rejects_missing_service_credentials(): void
    {
        $this->getJson('/api/v1/internal/analytics/initial-sync/maintenance-orders')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized service request.');
    }
}
