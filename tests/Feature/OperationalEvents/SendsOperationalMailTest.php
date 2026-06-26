<?php

namespace Tests\Feature\OperationalEvents;

use App\Enums\MaintenanceOrderStatus;
use App\Enums\SystemRole;
use App\Jobs\SendOperationalEventMailJob;
use App\Mail\OperationalMaintenanceOrderMail;
use App\Models\OperationalEventOutbox;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class SendsOperationalMailTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_operational_mail_is_sent_to_owner_for_scheduled_order_event(): void
    {
        Mail::fake();

        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'mail.advisor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'mail.manager@example.com']);
        $workshop = $this->workshopFor($manager, [
            'name' => 'North Service Center',
            'address' => '123 North Ave',
            'city' => 'Bogota',
        ]);
        $technician = $this->technicianFor($workshop, ['email' => 'mail.technician@example.com']);
        $owner = $this->ownerFor(['email' => 'mail.owner@example.com']);
        $scheduledAt = now()->addHour();
        $vehicle = $this->vehicleFor($owner, [
            'license_plate' => 'MAIL123',
        ]);
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Scheduled->value,
            'scheduled_at' => $scheduledAt,
        ]);
        $event = $this->outboxEvent([
            'event_type' => 'maintenance_order.scheduled.v1',
            'aggregate_id' => $order->id,
        ]);

        (new SendOperationalEventMailJob($event->id))->handle();

        Mail::assertSent(
            OperationalMaintenanceOrderMail::class,
            function (OperationalMaintenanceOrderMail $mail) use ($advisor, $manager, $owner, $scheduledAt, $technician): bool {
                $html = $mail->render();

                return $mail->hasTo($owner->email)
                    && ! $mail->hasTo($advisor->email)
                    && ! $mail->hasTo($technician->email)
                    && ! $mail->hasTo($manager->email)
                    && $mail->eventType === 'maintenance_order.scheduled.v1'
                    && str_contains($html, 'Your maintenance appointment')
                    && str_contains($html, 'North Service Center')
                    && str_contains($html, '123 North Ave, Bogota')
                    && str_contains($html, $scheduledAt->format('Y-m-d H:i'));
            },
        );
    }

    public function test_operational_mail_tells_owner_when_vehicle_is_ready_for_pickup(): void
    {
        Mail::fake();

        $advisor = $this->userWithRole(SystemRole::Advisor, ['email' => 'mail.pickup.advisor@example.com']);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, ['email' => 'mail.pickup.manager@example.com']);
        $workshop = $this->workshopFor($manager, [
            'name' => 'South Service Center',
            'address' => '456 South St',
            'city' => 'Medellin',
        ]);
        $technician = $this->technicianFor($workshop, ['email' => 'mail.pickup.technician@example.com']);
        $owner = $this->ownerFor(['email' => 'mail.pickup.owner@example.com']);
        $finishedAt = now();
        $vehicle = $this->vehicleFor($owner, [
            'license_plate' => 'PICK123',
        ]);
        $order = $this->maintenanceOrderFor($vehicle, $advisor, [
            'workshop_id' => $workshop->id,
            'technician_id' => $technician->id,
            'status' => MaintenanceOrderStatus::Completed->value,
            'finished_at' => $finishedAt,
        ]);
        $event = $this->outboxEvent([
            'event_type' => 'maintenance_order.completed.v1',
            'aggregate_id' => $order->id,
        ]);

        (new SendOperationalEventMailJob($event->id))->handle();

        Mail::assertSent(
            OperationalMaintenanceOrderMail::class,
            function (OperationalMaintenanceOrderMail $mail) use ($advisor, $finishedAt, $manager, $owner, $technician): bool {
                $html = $mail->render();

                return $mail->hasTo($owner->email)
                    && ! $mail->hasTo($advisor->email)
                    && ! $mail->hasTo($technician->email)
                    && ! $mail->hasTo($manager->email)
                    && $mail->eventType === 'maintenance_order.completed.v1'
                    && str_contains($html, 'ready for pickup')
                    && str_contains($html, 'South Service Center')
                    && str_contains($html, '456 South St, Medellin')
                    && str_contains($html, $finishedAt->format('Y-m-d H:i'));
            },
        );
    }

    public function test_operational_mail_ignores_unsupported_events(): void
    {
        Mail::fake();

        $event = $this->outboxEvent([
            'event_type' => 'maintenance_order.updated.v1',
            'aggregate_id' => 999,
        ]);

        (new SendOperationalEventMailJob($event->id))->handle();

        Mail::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function outboxEvent(array $attributes = []): OperationalEventOutbox
    {
        return OperationalEventOutbox::query()->create(array_merge([
            'event_id' => (string) str()->uuid(),
            'event_type' => 'maintenance_order.scheduled.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => 1,
            'actor_id' => null,
            'payload' => [
                'aggregate' => [
                    'type' => 'maintenance_order',
                    'id' => 1,
                ],
                'data' => [],
            ],
            'targets' => [
                'workshop_id' => null,
                'workshop_manager_id' => null,
                'technician_id' => null,
                'advisor_id' => null,
            ],
            'occurred_at' => now(),
        ], $attributes));
    }
}
