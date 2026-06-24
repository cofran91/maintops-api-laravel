<?php

namespace App\Models;

use Database\Factories\VehicleSystemFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name',
])]
class VehicleSystem extends Model
{
    /** @use HasFactory<VehicleSystemFactory> */
    use Filterable, HasFactory;

    /**
     * @return BelongsToMany<Workshop>
     */
    public function workshops(): BelongsToMany
    {
        return $this->belongsToMany(Workshop::class)
            ->withTimestamps();
    }
}
