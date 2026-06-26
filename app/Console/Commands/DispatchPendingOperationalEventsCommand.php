<?php

namespace App\Console\Commands;

use App\Jobs\PublishOperationalEventJob;
use App\Models\OperationalEventOutbox;
use Illuminate\Console\Command;

final class DispatchPendingOperationalEventsCommand extends Command
{
    protected $signature = 'operational-events:dispatch {--limit=100 : Maximum pending outbox records to enqueue}';

    protected $description = 'Enqueue unpublished operational events for Redis Streams publication.';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $outboxIds = OperationalEventOutbox::query()
            ->whereNull('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $outboxIds->each(
            fn (int $outboxId): mixed => PublishOperationalEventJob::dispatch($outboxId)
                ->onQueue((string) config('queue.queues.events', 'events')),
        );

        $this->info(sprintf('%d pending operational event(s) enqueued.', $outboxIds->count()));

        return self::SUCCESS;
    }
}
