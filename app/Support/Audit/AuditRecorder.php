<?php

namespace App\Support\Audit;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;

final class AuditRecorder
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(Model $auditable, string $event, array $oldValues = [], array $newValues = []): void
    {
        $actor = request()->user();

        Audit::query()->create([
            'user_type' => $actor?->getMorphClass(),
            'user_id' => $actor?->getKey(),
            'event' => $event,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
