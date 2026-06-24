<?php

namespace App\Models;

use App\Enums\MaintenanceTaskStatus;
use Database\Factories\MaintenanceTaskFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'vehicle_id',
    'vehicle_system_id',
    'name',
    'code',
    'description',
    'estimated_duration_minutes',
    'status',
    'is_active',
])]
class MaintenanceTask extends Model implements AuditableContract
{
    /** @use HasFactory<MaintenanceTaskFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Vehicle, MaintenanceTask>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<VehicleSystem, MaintenanceTask>
     */
    public function vehicleSystem(): BelongsTo
    {
        return $this->belongsTo(VehicleSystem::class);
    }

    /**
     * @return BelongsToMany<MaintenancePlan>
     */
    public function maintenancePlans(): BelongsToMany
    {
        return $this->belongsToMany(MaintenancePlan::class)
            ->withTimestamps()
            ->orderBy('maintenance_plans.id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_duration_minutes' => 'integer',
            'status' => MaintenanceTaskStatus::class,
            'is_active' => 'boolean',
        ];
    }
}
