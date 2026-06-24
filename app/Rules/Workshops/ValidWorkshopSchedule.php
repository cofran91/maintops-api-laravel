<?php

namespace App\Rules\Workshops;

use App\Services\Workshops\WorkshopScheduleService;
use Illuminate\Validation\Validator;

final class ValidWorkshopSchedule
{
    public function validate(Validator $validator, mixed $schedule): void
    {
        if (! is_array($schedule)) {
            return;
        }

        foreach ($schedule as $day => $hours) {
            if (! is_string($day) || ! in_array($day, WorkshopScheduleService::DAYS, true)) {
                $validator->errors()->add(
                    'weekly_schedule.'.$day,
                    'The day must be monday, tuesday, wednesday, thursday, friday, saturday, or sunday.',
                );

                continue;
            }

            if (! is_array($hours)) {
                continue;
            }

            if (array_diff(array_keys($hours), ['opens_at', 'closes_at']) !== []) {
                $validator->errors()->add(
                    'weekly_schedule.'.$day,
                    'Each day only accepts opens_at and closes_at.',
                );
            }

            if (
                isset($hours['opens_at'], $hours['closes_at'])
                && is_string($hours['opens_at'])
                && is_string($hours['closes_at'])
                && $hours['closes_at'] <= $hours['opens_at']
            ) {
                $validator->errors()->add(
                    'weekly_schedule.'.$day.'.closes_at',
                    'The closing time must be after the opening time.',
                );
            }
        }
    }
}
