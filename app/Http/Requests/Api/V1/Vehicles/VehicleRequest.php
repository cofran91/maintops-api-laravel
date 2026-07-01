<?php

namespace App\Http\Requests\Api\V1\Vehicles;

use App\Http\Requests\Api\V1\ResourceRequest;
use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class VehicleRequest extends ResourceRequest
{
    protected function modelClass(): string
    {
        return Vehicle::class;
    }

    protected function routeParameter(): string
    {
        return 'vehicle';
    }

    protected function prepareForValidation(): void
    {
        $this->upperTrimmedString('license_plate');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $vehicleId = $this->routeModelKey();
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
