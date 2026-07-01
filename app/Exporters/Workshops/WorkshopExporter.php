<?php

namespace App\Exporters\Workshops;

use App\Exporters\BaseExporter;

final class WorkshopExporter extends BaseExporter
{
    protected function filePrefix(): string
    {
        return 'workshops';
    }

    protected function translationKey(): string
    {
        return 'workshops';
    }

    /**
     * @param  array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>  $columns
     */
    protected function dataSheet(array $columns): object
    {
        return new WorkshopDataSheet($columns, self::HEADER_COLOR, self::REQUIRED_HEADER_COLOR);
    }

    /**
     * @return array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>
     */
    protected function columns(): array
    {
        return [
            [
                'key' => 'manager_email',
                'label' => (string) __('exports.workshops.columns.manager_email'),
                'required' => true,
                'description' => (string) __('exports.workshops.descriptions.manager_email'),
                'example' => (string) __('exports.workshops.examples.manager_email'),
            ],
            [
                'key' => 'name',
                'label' => (string) __('exports.workshops.columns.name'),
                'required' => true,
                'description' => (string) __('exports.workshops.descriptions.name'),
                'example' => (string) __('exports.workshops.examples.name'),
            ],
            [
                'key' => 'code',
                'label' => (string) __('exports.workshops.columns.code'),
                'required' => true,
                'description' => (string) __('exports.workshops.descriptions.code'),
                'example' => (string) __('exports.workshops.examples.code'),
            ],
            [
                'key' => 'is_active',
                'label' => (string) __('exports.workshops.columns.is_active'),
                'required' => true,
                'description' => (string) __('exports.workshops.descriptions.is_active'),
                'example' => true,
            ],
            [
                'key' => 'address',
                'label' => (string) __('exports.workshops.columns.address'),
                'required' => false,
                'description' => (string) __('exports.workshops.descriptions.address'),
                'example' => (string) __('exports.workshops.examples.address'),
            ],
            [
                'key' => 'city',
                'label' => (string) __('exports.workshops.columns.city'),
                'required' => false,
                'description' => (string) __('exports.workshops.descriptions.city'),
                'example' => (string) __('exports.workshops.examples.city'),
            ],
            [
                'key' => 'phone',
                'label' => (string) __('exports.workshops.columns.phone'),
                'required' => false,
                'description' => (string) __('exports.workshops.descriptions.phone'),
                'example' => (string) __('exports.workshops.examples.phone'),
            ],
            [
                'key' => 'email',
                'label' => (string) __('exports.workshops.columns.email'),
                'required' => false,
                'description' => (string) __('exports.workshops.descriptions.email'),
                'example' => (string) __('exports.workshops.examples.email'),
            ],
            [
                'key' => 'vehicle_system_codes',
                'label' => (string) __('exports.workshops.columns.vehicle_system_codes'),
                'required' => true,
                'description' => (string) __('exports.workshops.descriptions.vehicle_system_codes'),
                'example' => (string) __('exports.workshops.examples.vehicle_system_codes'),
            ],
            [
                'key' => 'technician_emails',
                'label' => (string) __('exports.workshops.columns.technician_emails'),
                'required' => false,
                'description' => (string) __('exports.workshops.descriptions.technician_emails'),
                'example' => (string) __('exports.workshops.examples.technician_emails'),
            ],
            [
                'key' => 'weekly_schedule',
                'label' => (string) __('exports.workshops.columns.weekly_schedule'),
                'required' => true,
                'description' => (string) __('exports.workshops.descriptions.weekly_schedule'),
                'example' => (string) __('exports.workshops.examples.weekly_schedule'),
            ],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function documentationColumnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 16,
            'C' => 82,
            'D' => 70,
        ];
    }
}
