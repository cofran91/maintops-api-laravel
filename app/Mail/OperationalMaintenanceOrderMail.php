<?php

namespace App\Mail;

use App\Models\MaintenanceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OperationalMaintenanceOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly MaintenanceOrder $order,
        public readonly string $eventType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->eventType) {
                'maintenance_order.completed.v1' => 'Your MaintOps vehicle is ready for pickup',
                default => 'Your MaintOps maintenance appointment was scheduled',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.operational-maintenance-order',
        );
    }
}
