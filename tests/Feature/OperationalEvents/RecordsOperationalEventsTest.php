<?php

namespace Tests\Feature\OperationalEvents;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Jobs\PublishOperationalEventJob;
use App\Jobs\SendOperationalEventMailJob;
use App\Models\MaintenanceOrder;
use App\Models\OperationalEventOutbox;
use App\States\MaintenanceOrders\OrderPendingOwnerApproval;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class RecordsOperationalEventsTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_order_creation_records_transactional_outbox_event(): void
    {
        Queue::fake();

        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'events.create.admin@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'events.create.advisor@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'events.create.owner@example.com']));

        $response = $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-orders', $this->maintenanceOrderPayload($vehicle, $advisor))
            ->assertCreated();

        $event = OperationalEventOutbox::query()
            ->where('event_type', 'maintenance_order.created.v1')
            ->where('aggregate_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame('maintenance_order', $event->aggregate_type);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($advisor->id, $event->targets['advisor_id']);
        $this->assertSame(MaintenanceOrderStatus::Created->value, $event->payload['data']['status']);
        $this->assertSame($vehicle->id, $event->payload['data']['vehicle_id']);
        $this->assertNull($event->published_at);
        $this->assertSame(0, $event->attempts);

        Queue::assertPushed(
            PublishOperationalEventJob::class,
            fn (PublishOperationalEventJob $job): bool => $job->outboxId === $event->id
                && $job->queue === 'events',
        );
    }

    public function test_outbox_is_rolled_back_with_failed_operational_transaction(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'events.rollback.advisor@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'events.rollback.owner@example.com']));

        try {
            DB::transaction(function () use ($vehicle, $advisor): void {
                MaintenanceOrder::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'advisor_id' => $advisor->id,
                    'status' => MaintenanceOrderStatus::Created->value,
                ]);

                throw new RuntimeException('forced rollback');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseMissing('maintenance_orders', [
            'vehicle_id' => $vehicle->id,
            'advisor_id' => $advisor->id,
        ]);
        $this->assertDatabaseCount('operational_event_outboxes', 0);
    }

    public function test_scheduled_order_event_queues_operational_mail(): void
    {
        Queue::fake();

        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'events.mail.advisor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'events.mail.manager@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'events.mail.technician@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'events.mail.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Approved->value,
        ]);

        $order->forceFill([
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ])->save();

        $event = OperationalEventOutbox::query()
            ->where('event_type', 'maintenance_order.scheduled.v1')
            ->where('aggregate_id', $order->id)
            ->firstOrFail();

        Queue::assertPushed(
            PublishOperationalEventJob::class,
            fn (PublishOperationalEventJob $job): bool => $job->outboxId === $event->id
                && $job->queue === 'events',
        );
        Queue::assertPushed(
            SendOperationalEventMailJob::class,
            fn (SendOperationalEventMailJob $job): bool => $job->outboxId === $event->id
                && $job->queue === 'mail',
        );
    }

    public function test_starting_item_records_order_and_item_events(): void
    {
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'events.start.advisor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'events.start.manager@example.com']);
        $workshop = $this->workshopFor($manager);
        $technician = $this->technicianFor($workshop, ['email' => 'events.start.technician@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'events.start.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
        $item = $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor(['vehicle_id' => $vehicle->id]), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);

        $this->withToken($technician->createToken('feature-test')->plainTextToken)
            ->putJson('/api/v1/maintenance-order-items/'.$item->id, [
                'status' => MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->assertOk();

        $this->assertDatabaseHas('operational_event_outboxes', [
            'event_type' => 'maintenance_order.in_progress.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => $order->id,
            'actor_id' => $technician->id,
        ]);
        $this->assertDatabaseHas('operational_event_outboxes', [
            'event_type' => 'maintenance_order_item.in_progress.v1',
            'aggregate_type' => 'maintenance_order_item',
            'aggregate_id' => $item->id,
            'actor_id' => $technician->id,
        ]);
    }

    public function test_submitting_order_for_owner_approval_records_only_the_order_event(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'events.submit.admin@example.com']);
        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'events.submit.advisor@example.com']);
        $vehicle = $this->vehicleFor($this->ownerFor(['email' => 'events.submit.owner@example.com']));
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'status' => MaintenanceOrderStatus::Created->value,
        ]);
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'pending_owner_approval_at' => null,
        ]);
        $this->maintenanceOrderItemFor($order, $this->maintenanceTaskFor(), [
            'status' => MaintenanceOrderItemStatus::Scheduled->value,
            'pending_owner_approval_at' => null,
        ]);

        $this->actingAs($admin, 'sanctum');

        $order->status->transitionTo(OrderPendingOwnerApproval::class);

        $this->assertDatabaseHas('operational_event_outboxes', [
            'event_type' => 'maintenance_order.pending_owner_approval.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => $order->id,
            'actor_id' => $admin->id,
        ]);

        $this->assertDatabaseMissing('operational_event_outboxes', [
            'event_type' => 'maintenance_order_item.pending_owner_approval.v1',
        ]);
    }
}
