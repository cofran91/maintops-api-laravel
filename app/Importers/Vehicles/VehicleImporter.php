<?php

namespace App\Importers\Vehicles;

use App\Importers\BaseImporter;
use App\Models\Owner;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class VehicleImporter extends BaseImporter
{
    /**
     * @return array<string, int>
     */
    protected function columnMap(): array
    {
        return [
            'owner_email' => 1,
            'license_plate' => 2,
            'brand' => 3,
            'model' => 4,
            'year' => 5,
            'color' => 6,
            'odometer_km' => 7,
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>
     */
    protected function payloadFromRow(array $row, array $columnMap): array
    {
        return [
            'owner_email' => $this->email($row[$columnMap['owner_email'] ?? 0] ?? null),
            'license_plate' => $this->licensePlate($row[$columnMap['license_plate'] ?? 0] ?? null),
            'brand' => $this->nullableString($row[$columnMap['brand'] ?? 0] ?? null),
            'model' => $this->nullableString($row[$columnMap['model'] ?? 0] ?? null),
            'year' => $this->integer($row[$columnMap['year'] ?? 0] ?? null),
            'color' => $this->nullableString($row[$columnMap['color'] ?? 0] ?? null),
            'odometer_km' => $this->integer($row[$columnMap['odometer_km'] ?? 0] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchingRecord(array $payload): ?Model
    {
        if (! is_string($payload['license_plate'] ?? null) || $payload['license_plate'] === '') {
            return null;
        }

        return Vehicle::query()
            ->where('license_plate', $payload['license_plate'])
            ->first();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(?Model $record): array
    {
        $vehicleId = $record?->getKey();
        $maxYear = now()->addYear()->year;

        return [
            'owner_email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                function (string $attribute, mixed $value, mixed $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    if (! $this->ownerForEmail($value) instanceof Owner) {
                        $fail(__('validation.exists', ['attribute' => $this->attributes()[$attribute] ?? $attribute]));
                    }
                },
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

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'owner_email' => __('exports.vehicles.columns.owner_email'),
            'license_plate' => __('exports.vehicles.columns.license_plate'),
            'brand' => __('exports.vehicles.columns.brand'),
            'model' => __('exports.vehicles.columns.model'),
            'year' => __('exports.vehicles.columns.year'),
            'color' => __('exports.vehicles.columns.color'),
            'odometer_km' => __('exports.vehicles.columns.odometer_km'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function vehicleData(array $data): array
    {
        /** @var Owner $owner */
        $owner = $this->ownerForEmail((string) $data['owner_email']);

        return [
            'owner_id' => $owner->getKey(),
            'license_plate' => $data['license_plate'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'odometer_km' => $data['odometer_km'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return self::CREATED|self::UPDATED
     */
    protected function persist(array $data, ?Model $record): string
    {
        $vehicleData = $this->vehicleData($data);

        if ($record instanceof Vehicle) {
            $record->update($vehicleData);

            return self::UPDATED;
        }

        Vehicle::query()->create($vehicleData);

        return self::CREATED;
    }

    private function ownerForEmail(string $email): ?Owner
    {
        return Owner::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();
    }

    private function licensePlate(mixed $value): ?string
    {
        $licensePlate = $this->nullableString($value);

        return $licensePlate === null ? null : Str::upper($licensePlate);
    }
}
