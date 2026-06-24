<?php

namespace App\Http\Requests\Api\V1\MaintenanceTasks;

use App\Enums\SystemRole;
use App\Models\MaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', MaintenanceTask::class);
        }

        $maintenanceTask = $this->route('maintenance_task');

        return $maintenanceTask instanceof MaintenanceTask
            && $actor->can('update', $maintenanceTask);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('vehicle_id') && $this->input('vehicle_id') === '') {
            $this->merge(['vehicle_id' => null]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => Str::upper(Str::slug((string) $this->input('code'), '-')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maintenanceTask = $this->route('maintenance_task');
        $maintenanceTaskId = $maintenanceTask instanceof MaintenanceTask ? $maintenanceTask->getKey() : null;
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
