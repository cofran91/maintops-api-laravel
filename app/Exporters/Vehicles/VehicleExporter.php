<?php

namespace App\Exporters\Vehicles;

use App\Exporters\BaseExporter;

final class VehicleExporter extends BaseExporter
{
    protected function filePrefix(): string
    {
        return 'vehicles';
    }

    protected function translationKey(): string
    {
        return 'vehicles';
    }

    /**
     * @param  array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>  $columns
     */
    protected function dataSheet(array $columns): object
    {
        return new VehicleDataSheet($columns, self::HEADER_COLOR, self::REQUIRED_HEADER_COLOR);
    }

    /**
     * @return array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>
     */
    protected function columns(): array
    {
        return [
            [
                'key' => 'owner_email',
                'label' => (string) __('exports.vehicles.columns.owner_email'),
                'required' => true,
                'description' => (string) __('exports.vehicles.descriptions.owner_email'),
                'example' => (string) __('exports.vehicles.examples.owner_email'),
            ],
            [
                'key' => 'license_plate',
                'label' => (string) __('exports.vehicles.columns.license_plate'),
                'required' => true,
                'description' => (string) __('exports.vehicles.descriptions.license_plate'),
                'example' => (string) __('exports.vehicles.examples.license_plate'),
            ],
            [
                'key' => 'brand',
                'label' => (string) __('exports.vehicles.columns.brand'),
                'required' => false,
                'description' => (string) __('exports.vehicles.descriptions.brand'),
                'example' => (string) __('exports.vehicles.examples.brand'),
            ],
            [
                'key' => 'model',
                'label' => (string) __('exports.vehicles.columns.model'),
                'required' => false,
                'description' => (string) __('exports.vehicles.descriptions.model'),
                'example' => (string) __('exports.vehicles.examples.model'),
            ],
            [
                'key' => 'year',
                'label' => (string) __('exports.vehicles.columns.year'),
                'required' => false,
                'description' => (string) __('exports.vehicles.descriptions.year'),
                'example' => 2024,
            ],
            [
                'key' => 'color',
                'label' => (string) __('exports.vehicles.columns.color'),
                'required' => false,
                'description' => (string) __('exports.vehicles.descriptions.color'),
                'example' => (string) __('exports.vehicles.examples.color'),
            ],
            [
                'key' => 'odometer_km',
                'label' => (string) __('exports.vehicles.columns.odometer_km'),
                'required' => true,
                'description' => (string) __('exports.vehicles.descriptions.odometer_km'),
                'example' => 15200,
            ],
        ];
    }
}
