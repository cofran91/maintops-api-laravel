<?php

namespace App\Http\Requests\Api\V1\MaintenancePlans;

use App\Http\Requests\Api\V1\ResourceRequest;
use App\Models\MaintenancePlan;
use Illuminate\Validation\Rule;

class MaintenancePlanRequest extends ResourceRequest
{
    protected function modelClass(): string
    {
        return MaintenancePlan::class;
    }

    protected function routeParameter(): string
    {
        return 'maintenance_plan';
    }

    protected function prepareForValidation(): void
    {
        $this->upperSlugString('code');

        foreach (['recommended_interval_days', 'recommended_interval_km'] as $field) {
            $this->emptyStringToNull($field);
        }

        $this->emptyValueToArray('task_ids');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maintenancePlanId = $this->routeModelKey();

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('maintenance_plans', 'code')->ignore($maintenancePlanId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'recommended_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'recommended_interval_km' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('maintenance_tasks', 'id')
                    ->whereNull('deleted_at')
                    ->whereNull('vehicle_id')
                    ->where('is_active', true),
            ],
        ];
    }
}
