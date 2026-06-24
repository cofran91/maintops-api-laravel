<?php

namespace App\Http\Resources\Api\V1\MaintenancePlans;

use App\Http\Resources\Api\V1\MaintenanceTasks\MaintenanceTaskResource;
use App\Models\MaintenancePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenancePlan
 */
class MaintenancePlanResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'recommended_interval_days' => $this->recommended_interval_days,
            'recommended_interval_km' => $this->recommended_interval_km,
            'task_ids' => $this->whenLoaded(
                'tasks',
                fn (): array => $this->tasks->pluck('id')->values()->all(),
            ),
            'tasks' => MaintenanceTaskResource::collection($this->whenLoaded('tasks')),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
