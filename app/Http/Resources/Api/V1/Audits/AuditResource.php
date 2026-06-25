<?php

namespace App\Http\Resources\Api\V1\Audits;

use App\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Audit
 */
final class AuditResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'actor' => [
                'type' => $this->user_type,
                'id' => $this->user_id,
                'resource' => $this->whenLoaded('user'),
            ],
            'auditable' => [
                'type' => $this->auditable_type,
                'id' => $this->auditable_id,
                'resource' => $this->whenLoaded('auditable'),
            ],
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'url' => $this->url,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'tags' => $this->tags,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
