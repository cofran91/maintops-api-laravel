<?php

namespace App\Models;

use App\Enums\MaintenanceOrderItemStatus;
use Database\Factories\MaintenanceOrderItemFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'maintenance_order_id',
    'maintenance_task_id',
    'maintenance_plan_id',
    'odometer_km',
    'planned_duration_minutes',
    'status',
    'pending_owner_approval_at',
    'scheduled_at',
    'scheduled_ends_at',
    'started_at',
    'finished_at',
    'rejected_at',
    'cancelled_at',
])]
class MaintenanceOrderItem extends Model implements AuditableContract
{
    /** @use HasFactory<MaintenanceOrderItemFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<MaintenanceOrder, MaintenanceOrderItem>
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    /**
     * @return BelongsTo<MaintenanceTask, MaintenanceOrderItem>
     */
    public function maintenanceTask(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTask::class);
    }

    /**
     * @return BelongsTo<MaintenancePlan, MaintenanceOrderItem>
     */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'odometer_km' => 'integer',
            'planned_duration_minutes' => 'integer',
            'status' => MaintenanceOrderItemStatus::class,
            'pending_owner_approval_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'scheduled_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
