<?php

namespace App\Models;

use Database\Factories\VehicleSystemFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
])]
class VehicleSystem extends Model
{
    /** @use HasFactory<VehicleSystemFactory> */
    use Filterable, HasFactory;
}
