<?php

namespace App\Enums;

enum MaintenanceTaskStatus: string
{
    case Created = 'created';
    case Scheduled = 'scheduled';
    case Started = 'started';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
