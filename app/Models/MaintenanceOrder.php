<?php

namespace App\Models;

use App\Enums\MaintenanceOrderStatus;
use Database\Factories\MaintenanceOrderFactory;
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
    'vehicle_id',
    'advisor_id',
    'workshop_id',
    'technician_id',
    'status',
    'scheduled_at',
    'started_at',
    'finished_at',
    'delivered_at',
    'cancelled_at',
])]
class MaintenanceOrder extends Model implements AuditableContract
{
    /** @use HasFactory<MaintenanceOrderFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Vehicle, MaintenanceOrder>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, MaintenanceOrder>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /**
     * @return BelongsTo<Workshop, MaintenanceOrder>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * @return BelongsTo<User, MaintenanceOrder>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * @return HasMany<MaintenanceOrderItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceOrderItem::class)
            ->orderBy('maintenance_order_items.id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MaintenanceOrderStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
