<?php

namespace Tests\Feature\OperationalEvents;

use App\Jobs\PublishOperationalEventJob;
use App\Models\OperationalEventOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PublishesOperationalEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_publishes_operational_event_to_redis_stream(): void
    {
        config(['operations.events.stream' => 'test:ops:events']);

        $outbox = $this->outboxEvent([
            'event_type' => 'maintenance_order.scheduled.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => 123,
            'actor_id' => 45,
            'payload' => [
                'aggregate' => [
                    'type' => 'maintenance_order',
                    'id' => 123,
                ],
                'data' => [
                    'status' => 'scheduled',
                    'scheduled_at' => now()->addHour()->toISOString(),
                ],
            ],
            'targets' => [
                'workshop_id' => 10,
                'workshop_manager_id' => 11,
                'technician_id' => 12,
                'advisor_id' => 45,
            ],
        ]);

        $streamFields = null;
        $client = Mockery::mock();
        $client->shouldReceive('xAdd')
            ->once()
            ->withArgs(function (string $stream, string $id, array $fields) use (&$streamFields, $outbox): bool {
                $streamFields = $fields;

                return $stream === 'test:ops:events'
                    && $id === '*'
                    && $fields['event_id'] === $outbox->event_id
                    && $fields['event_type'] === 'maintenance_order.scheduled.v1'
                    && $fields['aggregate_type'] === 'maintenance_order'
                    && $fields['aggregate_id'] === '123';
            });

        $connection = Mockery::mock();
        $connection->shouldReceive('client')->once()->andReturn($client);
        Redis::shouldReceive('connection')->once()->with('streams')->andReturn($connection);

        (new PublishOperationalEventJob($outbox->id))->handle();

        $outbox->refresh();
        $payload = json_decode($streamFields['payload'], true, flags: JSON_THROW_ON_ERROR);
        $targets = json_decode($streamFields['targets'], true, flags: JSON_THROW_ON_ERROR);

        $this->assertNotNull($outbox->published_at);
        $this->assertSame(0, $outbox->attempts);
        $this->assertNull($outbox->last_error);
        $this->assertSame($outbox->event_id, $payload['event_id']);
        $this->assertSame('maintenance_order.scheduled.v1', $payload['event_type']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame(45, $payload['actor']['user_id']);
        $this->assertSame(10, $payload['targets']['workshop_id']);
        $this->assertSame(123, $payload['data']['maintenance_order_id']);
        $this->assertSame(['11', '12', '45'], $targets['user_ids']);
        $this->assertSame(['10'], $targets['workshop_ids']);
        $this->assertSame([], $targets['roles']);
    }

    public function test_job_publishes_role_targets_for_administrative_events(): void
    {
        config(['operations.events.stream' => 'test:ops:events']);

        $outbox = $this->outboxEvent([
            'event_type' => 'user.created.v1',
            'aggregate_type' => 'user',
            'aggregate_id' => 99,
            'targets' => [
                'roles' => ['admin', 'super_admin'],
            ],
        ]);

        $streamFields = null;
        $client = Mockery::mock();
        $client->shouldReceive('xAdd')
            ->once()
            ->withArgs(function (string $stream, string $id, array $fields) use (&$streamFields): bool {
                $streamFields = $fields;

                return $stream === 'test:ops:events'
                    && $id === '*'
                    && $fields['event_type'] === 'user.created.v1';
            });

        $connection = Mockery::mock();
        $connection->shouldReceive('client')->once()->andReturn($client);
        Redis::shouldReceive('connection')->once()->with('streams')->andReturn($connection);

        (new PublishOperationalEventJob($outbox->id))->handle();

        $targets = json_decode($streamFields['targets'], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['admin', 'super_admin'], $targets['roles']);
        $this->assertSame([], $targets['user_ids']);
        $this->assertSame([], $targets['workshop_ids']);
    }

    public function test_job_tracks_failed_publication_attempts(): void
    {
        $outbox = $this->outboxEvent([
            'event_type' => 'maintenance_order.completed.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => 456,
        ]);

        $client = Mockery::mock();
        $client->shouldReceive('xAdd')
            ->once()
            ->andThrow(new RuntimeException('Redis is unavailable'));

        $connection = Mockery::mock();
        $connection->shouldReceive('client')->once()->andReturn($client);
        Redis::shouldReceive('connection')->once()->with('streams')->andReturn($connection);

        try {
            (new PublishOperationalEventJob($outbox->id))->handle();
            $this->fail('The publication failure was not rethrown.');
        } catch (RuntimeException) {
            $outbox->refresh();

            $this->assertNull($outbox->published_at);
            $this->assertSame(1, $outbox->attempts);
            $this->assertStringContainsString('Redis is unavailable', $outbox->last_error);
        }
    }

    public function test_command_dispatches_pending_unpublished_events(): void
    {
        Queue::fake();

        $firstPending = $this->outboxEvent([
            'event_type' => 'maintenance_order.created.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => 1,
        ]);
        $this->outboxEvent([
            'event_type' => 'maintenance_order.scheduled.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => 2,
        ]);
        $this->outboxEvent([
            'event_type' => 'maintenance_order.completed.v1',
            'aggregate_type' => 'maintenance_order',
            'aggregate_id' => 3,
            'published_at' => now(),
        ]);

        $this->artisan('operational-events:dispatch --limit=1')
            ->assertSuccessful()
            ->expectsOutput('1 pending operational event(s) enqueued.');

        Queue::assertPushed(PublishOperationalEventJob::class, 1);
        Queue::assertPushed(
            PublishOperationalEventJob::class,
            fn (PublishOperationalEventJob $job): bool => $job->outboxId === $firstPending->id
                && $job->queue === 'events',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function outboxEvent(array $attributes = []): OperationalEventOutbox
    {
        return OperationalEventOutbox::query()->create(array_merge([
            'event_id' => (string) str()->uuid(),
            'event_type' => 'maintenance_order.created.v1',
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
