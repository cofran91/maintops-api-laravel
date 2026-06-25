<?php

namespace App\Jobs;

use App\Models\OperationalEventOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

final class PublishOperationalEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    public function __construct(
        public readonly int $outboxId,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    public function handle(): void
    {
        $outbox = OperationalEventOutbox::query()->find($this->outboxId);

        if ($outbox === null || $outbox->published_at !== null) {
            return;
        }

        try {
            $payload = array_merge($outbox->payload ?? [], [
                'event_id' => $outbox->event_id,
                'event_type' => $outbox->event_type,
                'version' => 1,
                'occurred_at' => $outbox->occurred_at->toISOString(),
                'actor' => [
                    'user_id' => $outbox->actor_id,
                ],
                'targets' => $outbox->targets ?? [],
            ]);

            $payload['aggregate'] = is_array($payload['aggregate'] ?? null)
                ? $payload['aggregate']
                : [
                    'type' => $outbox->aggregate_type,
                    'id' => (int) $outbox->aggregate_id,
                ];

            $payload['data'] = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            if ($outbox->aggregate_type === 'maintenance_order') {
                $payload['data']['maintenance_order_id'] ??= (int) $outbox->aggregate_id;
            }

            if ($outbox->aggregate_type === 'maintenance_order_item') {
                $payload['data']['maintenance_order_item_id'] ??= (int) $outbox->aggregate_id;
            }

            Redis::connection('streams')->client()->xAdd(
                config('operations.events.stream'),
                '*',
                [
                    'event_id' => $outbox->event_id,
                    'event_type' => $outbox->event_type,
                    'aggregate_type' => $outbox->aggregate_type,
                    'aggregate_id' => (string) $outbox->aggregate_id,
                    'occurred_at' => $outbox->occurred_at->toISOString(),
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            );

            $outbox->forceFill([
                'published_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $outbox->increment('attempts', 1, [
                'last_error' => Str::limit($exception->getMessage(), 60000),
            ]);

            throw $exception;
        }
    }
}
