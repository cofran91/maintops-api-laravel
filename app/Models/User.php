<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'is_active',
    'phone',
    'document_number',
    'address',
    'workshop_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, Filterable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    /**
     * @var array<int, string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * @return HasOne<Workshop>
     */
    public function managedWorkshop(): HasOne
    {
        return $this->hasOne(Workshop::class, 'manager_user_id');
    }

    /**
     * @return BelongsTo<Workshop, User>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * @return HasMany<MaintenanceOrder>
     */
    public function advisedMaintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'advisor_id')
            ->orderBy('maintenance_orders.id');
    }

    /**
     * @return HasMany<MaintenanceOrder>
     */
    public function assignedMaintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'technician_id')
            ->orderBy('maintenance_orders.id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
