<?php

namespace App\Http\Requests\Api\V1\MaintenanceTasks;

use App\Enums\SystemRole;
use App\Http\Requests\Api\V1\ResourceRequest;
use App\Models\MaintenanceTask;
use Illuminate\Validation\Rule;

class MaintenanceTaskRequest extends ResourceRequest
{
    protected function modelClass(): string
    {
        return MaintenanceTask::class;
    }

    protected function routeParameter(): string
    {
        return 'maintenance_task';
    }

    protected function prepareForValidation(): void
    {
        $this->emptyStringToNull('vehicle_id');
        $this->upperSlugString('code');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maintenanceTaskId = $this->routeModelKey();
        $vehicleIdPresence = $this->user()?->hasRole(SystemRole::Advisor->value) ? 'required' : 'nullable';

        return [
            'vehicle_id' => [
                $vehicleIdPresence,
                'integer',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
            ],
            'vehicle_system_id' => [
                'required',
                'integer',
                Rule::exists('vehicle_systems', 'id'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_tasks', 'name')->ignore($maintenanceTaskId),
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('maintenance_tasks', 'code')->ignore($maintenanceTaskId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'is_active' => ['required', 'boolean'],
            'status' => ['prohibited'],
        ];
    }
}
