<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_id',
    'event_type',
    'aggregate_type',
    'aggregate_id',
    'actor_id',
    'payload',
    'targets',
    'occurred_at',
    'published_at',
    'attempts',
    'last_error',
])]
class OperationalEventOutbox extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'actor_id' => 'integer',
            'payload' => 'array',
            'targets' => 'array',
            'occurred_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
