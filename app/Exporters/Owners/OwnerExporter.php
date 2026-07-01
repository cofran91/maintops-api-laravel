<?php

namespace App\Exporters\Owners;

use App\Exporters\BaseExporter;

final class OwnerExporter extends BaseExporter
{
    protected function filePrefix(): string
    {
        return 'owners';
    }

    protected function translationKey(): string
    {
        return 'owners';
    }

    /**
     * @param  array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>  $columns
     */
    protected function dataSheet(array $columns): object
    {
        return new OwnerDataSheet($columns, self::HEADER_COLOR, self::REQUIRED_HEADER_COLOR);
    }

    /**
     * @return array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>
     */
    protected function columns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => (string) __('exports.owners.columns.name'),
                'required' => true,
                'description' => (string) __('exports.owners.descriptions.name'),
                'example' => (string) __('exports.owners.examples.name'),
            ],
            [
                'key' => 'email',
                'label' => (string) __('exports.owners.columns.email'),
                'required' => true,
                'description' => (string) __('exports.owners.descriptions.email'),
                'example' => (string) __('exports.owners.examples.email'),
            ],
            [
                'key' => 'is_active',
                'label' => (string) __('exports.owners.columns.is_active'),
                'required' => true,
                'description' => (string) __('exports.owners.descriptions.is_active'),
                'example' => true,
            ],
            [
                'key' => 'phone',
                'label' => (string) __('exports.owners.columns.phone'),
                'required' => false,
                'description' => (string) __('exports.owners.descriptions.phone'),
                'example' => (string) __('exports.owners.examples.phone'),
            ],
            [
                'key' => 'document_number',
                'label' => (string) __('exports.owners.columns.document_number'),
                'required' => false,
                'description' => (string) __('exports.owners.descriptions.document_number'),
                'example' => (string) __('exports.owners.examples.document_number'),
            ],
            [
                'key' => 'address',
                'label' => (string) __('exports.owners.columns.address'),
                'required' => false,
                'description' => (string) __('exports.owners.descriptions.address'),
                'example' => (string) __('exports.owners.examples.address'),
            ],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function documentationColumnWidths(): array
    {
        return [
            'A' => 26,
            'B' => 16,
            'C' => 72,
            'D' => 30,
        ];
    }
}
