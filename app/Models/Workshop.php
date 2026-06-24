<?php

namespace App\Models;

use Database\Factories\WorkshopFactory;
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
    'manager_user_id',
    'name',
    'code',
    'address',
    'city',
    'phone',
    'email',
    'weekly_schedule',
    'is_active',
])]
class Workshop extends Model implements AuditableContract
{
    /** @use HasFactory<WorkshopFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, Workshop>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * @return BelongsToMany<VehicleSystem>
     */
    public function vehicleSystems(): BelongsToMany
    {
        return $this->belongsToMany(VehicleSystem::class)
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekly_schedule' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
