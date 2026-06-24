<?php

namespace App\Services\MaintenancePlans;

use App\Models\MaintenancePlan;

final class MaintenancePlanAuditSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(MaintenancePlan $maintenancePlan): array
    {
        return [
            'attributes' => $maintenancePlan->attributesToArray(),
            'maintenance_task_ids' => $maintenancePlan->tasks()
                ->orderBy('maintenance_tasks.id')
                ->pluck('maintenance_tasks.id')
                ->all(),
        ];
    }
}
