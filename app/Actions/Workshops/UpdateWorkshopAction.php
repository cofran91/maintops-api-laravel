<?php

namespace App\Actions\Workshops;

use App\Models\User;
use App\Models\Workshop;
use App\Services\Workshops\WorkshopAuditSnapshotService;
use App\Services\Workshops\WorkshopScheduleService;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateWorkshopAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly WorkshopAuditSnapshotService $snapshotService,
        private readonly WorkshopScheduleService $scheduleService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Workshop $workshop, array $attributes): Workshop
    {
        return DB::transaction(function () use ($workshop, $attributes): Workshop {
            $oldValues = $this->snapshotService->snapshot($workshop);
            /** @var array<int, int> $vehicleSystemIds */
            $vehicleSystemIds = $attributes['vehicle_system_ids'];
            /** @var array<int, int> $technicianUserIds */
            $technicianUserIds = $attributes['technician_user_ids'];
            $attributes['weekly_schedule'] = $this->scheduleService->normalize($attributes['weekly_schedule']);

            /** @var Workshop $updatedWorkshop */
            $updatedWorkshop = Workshop::withoutAuditing(function () use ($workshop, $attributes, $vehicleSystemIds, $technicianUserIds): Workshop {
                $workshop->update(Arr::except($attributes, ['vehicle_system_ids', 'technician_user_ids']));
                $workshop->vehicleSystems()->sync($vehicleSystemIds);
                $this->syncTechnicians($workshop, $technicianUserIds);

                return $workshop->refresh();
            });

            $this->auditRecorder->record(
                $updatedWorkshop,
                'workshop updated',
                oldValues: $oldValues,
                newValues: $this->snapshotService->snapshot($updatedWorkshop),
            );

            return $updatedWorkshop;
        });
    }

    /**
     * @param  array<int, int>  $technicianUserIds
     */
    private function syncTechnicians(Workshop $workshop, array $technicianUserIds): void
    {
        $currentTechnicians = User::query()
            ->where('workshop_id', $workshop->getKey());

        if ($technicianUserIds !== []) {
            $currentTechnicians->whereNotIn('id', $technicianUserIds);
        }

        $currentTechnicians->update(['workshop_id' => null]);

        User::query()
            ->whereKey($technicianUserIds)
            ->update(['workshop_id' => $workshop->getKey()]);
    }
}
