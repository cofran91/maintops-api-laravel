<?php

namespace App\Http\Requests\Api\V1\MaintenancePlans;

use App\Models\MaintenancePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', MaintenancePlan::class);
        }

        $maintenancePlan = $this->route('maintenance_plan');

        return $maintenancePlan instanceof MaintenancePlan
            && $actor->can('update', $maintenancePlan);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => Str::upper(Str::slug((string) $this->input('code'), '-')),
            ]);
        }

        foreach (['recommended_interval_days', 'recommended_interval_km'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('task_ids') && ($this->input('task_ids') === null || $this->input('task_ids') === '')) {
            $this->merge(['task_ids' => []]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maintenancePlan = $this->route('maintenance_plan');
        $maintenancePlanId = $maintenancePlan instanceof MaintenancePlan ? $maintenancePlan->getKey() : null;

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
