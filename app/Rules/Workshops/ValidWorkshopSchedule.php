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
                    __('api.validation.rules.schedule_day_invalid'),
                );

                continue;
            }

            if (! is_array($hours)) {
                continue;
            }

            if (array_diff(array_keys($hours), ['opens_at', 'closes_at']) !== []) {
                $validator->errors()->add(
                    'weekly_schedule.'.$day,
                    __('api.validation.rules.schedule_allowed_keys'),
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
                    __('api.validation.rules.schedule_closing_after_opening'),
                );
            }
        }
    }
}
