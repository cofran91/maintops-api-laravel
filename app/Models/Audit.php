<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_type',
    'user_id',
    'event',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'url',
    'ip_address',
    'user_agent',
    'tags',
])]
class Audit extends Model
{
    use Filterable;

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
