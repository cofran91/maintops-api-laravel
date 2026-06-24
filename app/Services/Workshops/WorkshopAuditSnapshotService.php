<?php

namespace App\Services\Workshops;

use App\Models\Workshop;

final class WorkshopAuditSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Workshop $workshop): array
    {
        return [
            'attributes' => $workshop->attributesToArray(),
            'vehicle_system_ids' => $workshop->vehicleSystems()
                ->orderBy('vehicle_systems.id')
                ->pluck('vehicle_systems.id')
                ->all(),
            'technician_user_ids' => $workshop->technicians()
                ->orderBy('users.id')
                ->pluck('users.id')
                ->all(),
        ];
    }
}
