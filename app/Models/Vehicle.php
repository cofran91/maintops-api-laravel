<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'owner_id',
    'license_plate',
    'brand',
    'model',
    'year',
    'color',
    'odometer_km',
])]
class Vehicle extends Model implements AuditableContract
{
    /** @use HasFactory<VehicleFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Owner, Vehicle>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * @return HasMany<MaintenanceTask>
     */
    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class)
            ->orderBy('maintenance_tasks.id');
    }

    /**
     * @return HasMany<MaintenanceOrder>
     */
    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)
            ->orderBy('maintenance_orders.id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'odometer_km' => 'integer',
        ];
    }
}
