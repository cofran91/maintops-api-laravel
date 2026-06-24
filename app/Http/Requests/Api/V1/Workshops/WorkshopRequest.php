<?php

namespace App\Http\Requests\Api\V1\Workshops;

use App\Models\Workshop;
use App\Rules\Workshops\AssignableWorkshopManager;
use App\Rules\Workshops\ValidWorkshopSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkshopRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', Workshop::class);
        }

        $workshop = $this->route('workshop');

        return $workshop instanceof Workshop
            && $actor->can('update', $workshop);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower(trim((string) $this->input('email'))),
            ]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => Str::upper(Str::slug((string) $this->input('code'), '-')),
            ]);
        }

        if ($this->has('weekly_schedule') && is_array($this->input('weekly_schedule'))) {
            $normalizedSchedule = [];

            foreach ($this->input('weekly_schedule') as $day => $hours) {
                $normalizedSchedule[Str::lower((string) $day)] = $hours;
            }

            $this->merge(['weekly_schedule' => $normalizedSchedule]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workshop = $this->route('workshop');
        $workshopId = $workshop instanceof Workshop ? $workshop->getKey() : null;

        return [
            'manager_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                new AssignableWorkshopManager,
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
