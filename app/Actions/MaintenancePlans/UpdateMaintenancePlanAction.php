<?php

namespace App\Actions\MaintenancePlans;

use App\Models\MaintenancePlan;
use App\Services\MaintenancePlans\MaintenancePlanAuditSnapshotService;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateMaintenancePlanAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly MaintenancePlanAuditSnapshotService $snapshotService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(MaintenancePlan $maintenancePlan, array $attributes): MaintenancePlan
    {
        return DB::transaction(function () use ($maintenancePlan, $attributes): MaintenancePlan {
            $oldValues = $this->snapshotService->snapshot($maintenancePlan);
            /** @var array<int, int> $taskIds */
            $taskIds = $attributes['task_ids'];

            /** @var MaintenancePlan $updatedMaintenancePlan */
            $updatedMaintenancePlan = MaintenancePlan::withoutAuditing(function () use ($maintenancePlan, $attributes, $taskIds): MaintenancePlan {
                $maintenancePlan->update(Arr::except($attributes, ['task_ids']));
                $maintenancePlan->tasks()->sync($taskIds);

                return $maintenancePlan->refresh();
            });

            $this->auditRecorder->record(
                $updatedMaintenancePlan,
                'maintenance plan updated',
                oldValues: $oldValues,
                newValues: $this->snapshotService->snapshot($updatedMaintenancePlan),
            );

            return $updatedMaintenancePlan;
        });
    }
}
