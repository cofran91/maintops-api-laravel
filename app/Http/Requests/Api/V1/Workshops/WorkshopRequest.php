<?php

namespace App\Http\Requests\Api\V1\Workshops;

use App\Http\Requests\Api\V1\ResourceRequest;
use App\Models\Workshop;
use App\Rules\Workshops\AssignableWorkshopManager;
use App\Rules\Workshops\AssignableWorkshopTechnician;
use App\Rules\Workshops\ValidWorkshopSchedule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkshopRequest extends ResourceRequest
{
    protected function modelClass(): string
    {
        return Workshop::class;
    }

    protected function routeParameter(): string
    {
        return 'workshop';
    }

    protected function prepareForValidation(): void
    {
        $this->lowerTrimmedString('email');
        $this->upperSlugString('code');
        $this->normalizeArrayKeysToLower('weekly_schedule');
        $this->emptyValueToArray('technician_user_ids');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workshop = $this->routeModel();
        $workshopId = $this->routeModelKey();

        return [
            'manager_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                new AssignableWorkshopManager($workshop instanceof Workshop ? $workshop : null),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workshops', 'name')->ignore($workshopId),
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('workshops', 'code')->ignore($workshopId),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'weekly_schedule' => ['required', 'array', 'min:1'],
            'weekly_schedule.*' => ['required', 'array'],
            'weekly_schedule.*.opens_at' => ['required', 'date_format:H:i'],
            'weekly_schedule.*.closes_at' => ['required', 'date_format:H:i'],
            'vehicle_system_ids' => ['required', 'array', 'min:1'],
            'vehicle_system_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('vehicle_systems', 'id'),
            ],
            'technician_user_ids' => ['present', 'array'],
            'technician_user_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                new AssignableWorkshopTechnician($workshopId),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(ValidWorkshopSchedule::class)->validate($validator, $this->input('weekly_schedule'));
        });
    }
}
