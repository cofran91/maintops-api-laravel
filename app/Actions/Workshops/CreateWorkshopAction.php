<?php

namespace App\Actions\Workshops;

use App\Models\Workshop;
use App\Services\Workshops\WorkshopAuditSnapshotService;
use App\Services\Workshops\WorkshopScheduleService;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateWorkshopAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly WorkshopAuditSnapshotService $snapshotService,
        private readonly WorkshopScheduleService $scheduleService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Workshop
    {
        return DB::transaction(function () use ($attributes): Workshop {
            /** @var array<int, int> $vehicleSystemIds */
            $vehicleSystemIds = $attributes['vehicle_system_ids'];
            $attributes['weekly_schedule'] = $this->scheduleService->normalize($attributes['weekly_schedule']);

            /** @var Workshop $workshop */
            $workshop = Workshop::withoutAuditing(function () use ($attributes, $vehicleSystemIds): Workshop {
                $workshop = Workshop::query()->create(Arr::except($attributes, ['vehicle_system_ids']));
                $workshop->vehicleSystems()->sync($vehicleSystemIds);

                return $workshop->refresh();
            });

            $this->auditRecorder->record(
                $workshop,
                'workshop created',
                newValues: $this->snapshotService->snapshot($workshop),
            );

            return $workshop;
        });
    }
}
