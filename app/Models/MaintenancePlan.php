<?php

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'code',
    'name',
    'description',
    'recommended_interval_days',
    'recommended_interval_km',
    'is_active',
])]
class MaintenancePlan extends Model implements AuditableContract
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return BelongsToMany<MaintenanceTask>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceTask::class)
            ->withTimestamps()
            ->orderBy('maintenance_tasks.id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommended_interval_days' => 'integer',
            'recommended_interval_km' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
