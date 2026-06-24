<?php

namespace App\Models;

use Database\Factories\OwnerFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'name',
    'email',
    'is_active',
    'phone',
    'document_number',
    'address',
])]
class Owner extends Model implements AuditableContract
{
    /** @use HasFactory<OwnerFactory> */
    use Auditable, Filterable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
