<?php

namespace App\Http\Requests\Api\V1\Vehicles;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', Vehicle::class);
        }

        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            && $actor->can('update', $vehicle);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('license_plate')) {
            $this->merge([
                'license_plate' => Str::upper(trim((string) $this->input('license_plate'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $vehicleId = $vehicle instanceof Vehicle ? $vehicle->getKey() : null;
        $maxYear = now()->addYear()->year;

        return [
            'owner_id' => [
                'required',
                'integer',
                Rule::exists('owners', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'license_plate' => [
                'required',
                'string',
                'max:30',
                Rule::unique('vehicles', 'license_plate')->ignore($vehicleId),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.$maxYear],
            'color' => ['nullable', 'string', 'max:80'],
            'odometer_km' => ['required', 'integer', 'min:0'],
        ];
    }
}
