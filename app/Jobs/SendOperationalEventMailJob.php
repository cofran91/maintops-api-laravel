<?php

namespace App\Jobs;

use App\Mail\OperationalMaintenanceOrderMail;
use App\Models\MaintenanceOrder;
use App\Models\OperationalEventOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendOperationalEventMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SUPPORTED_EVENT_TYPES = [
        'maintenance_order.scheduled.v1',
        'maintenance_order.completed.v1',
    ];

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $outboxId,
    ) {}

    public function handle(): void
    {
        $outbox = OperationalEventOutbox::query()->find($this->outboxId);

        if (
            $outbox === null
            || $outbox->aggregate_type !== 'maintenance_order'
            || ! in_array($outbox->event_type, self::SUPPORTED_EVENT_TYPES, true)
        ) {
            return;
        }

        $order = MaintenanceOrder::query()
            ->with([
                'vehicle:id,owner_id,license_plate,brand,model',
                'vehicle.owner:id,name,email',
                'workshop:id,name,code,address,city',
            ])
            ->find($outbox->aggregate_id);

        if ($order === null) {
            return;
        }

        $ownerEmail = $order->vehicle?->owner?->email;

        if (! is_string($ownerEmail) || $ownerEmail === '') {
            return;
        }

        Mail::to($ownerEmail)->send(
            new OperationalMaintenanceOrderMail($order, $outbox->event_type),
        );
    }
}
