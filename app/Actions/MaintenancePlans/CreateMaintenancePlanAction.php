<?php

namespace App\Actions\MaintenancePlans;

use App\Models\MaintenancePlan;
use App\Services\MaintenancePlans\MaintenancePlanAuditSnapshotService;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateMaintenancePlanAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly MaintenancePlanAuditSnapshotService $snapshotService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): MaintenancePlan
    {
        return DB::transaction(function () use ($attributes): MaintenancePlan {
            /** @var array<int, int> $taskIds */
            $taskIds = $attributes['task_ids'];

            /** @var MaintenancePlan $maintenancePlan */
            $maintenancePlan = MaintenancePlan::withoutAuditing(function () use ($attributes, $taskIds): MaintenancePlan {
                $maintenancePlan = MaintenancePlan::query()->create(Arr::except($attributes, ['task_ids']));
                $maintenancePlan->tasks()->sync($taskIds);

                return $maintenancePlan->refresh();
            });

            $this->auditRecorder->record(
                $maintenancePlan,
                'maintenance plan created',
                newValues: $this->snapshotService->snapshot($maintenancePlan),
            );

            return $maintenancePlan;
        });
    }
}
