<?php

namespace App\Services\Workshops;

final class WorkshopScheduleService
{
    /**
     * @var array<int, string>
     */
    public const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, array{opens_at: string, closes_at: string}>
     */
    public function normalize(array $schedule): array
    {
        $normalized = [];

        foreach (self::DAYS as $day) {
            if (! isset($schedule[$day]) || ! is_array($schedule[$day])) {
                continue;
            }

            $normalized[$day] = [
                'opens_at' => (string) $schedule[$day]['opens_at'],
                'closes_at' => (string) $schedule[$day]['closes_at'],
            ];
        }

        return $normalized;
    }
}
