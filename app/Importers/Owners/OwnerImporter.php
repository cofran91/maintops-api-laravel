<?php

namespace App\Importers\Owners;

use App\Importers\BaseImporter;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class OwnerImporter extends BaseImporter
{
    /**
     * @return array<string, int>
     */
    protected function columnMap(): array
    {
        return [
            'name' => 1,
            'email' => 2,
            'is_active' => 3,
            'phone' => 4,
            'document_number' => 5,
            'address' => 6,
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
            'name' => $this->nullableString($row[$columnMap['name'] ?? 0] ?? null),
            'email' => $this->email($row[$columnMap['email'] ?? 0] ?? null),
            'is_active' => $this->boolean($row[$columnMap['is_active'] ?? 0] ?? null),
            'phone' => $this->nullableString($row[$columnMap['phone'] ?? 0] ?? null),
            'document_number' => $this->nullableString($row[$columnMap['document_number'] ?? 0] ?? null),
            'address' => $this->nullableString($row[$columnMap['address'] ?? 0] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchingRecord(array $payload): ?Model
    {
        if (! is_string($payload['email'] ?? null) || $payload['email'] === '') {
            return null;
        }

        return Owner::query()
            ->where('email', $payload['email'])
            ->where(function ($query) use ($payload): void {
                if ($payload['document_number'] === null) {
                    $query->whereNull('document_number');

                    return;
                }

                $query->where('document_number', $payload['document_number']);
            })
            ->first();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(?Model $record): array
    {
        $ownerId = $record?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('owners', 'email')->ignore($ownerId),
            ],
            'is_active' => [
                'required',
                function (string $attribute, mixed $value, mixed $fail): void {
                    if (! is_bool($value)) {
                        $fail(__('validation.boolean', ['attribute' => $this->attributes()[$attribute] ?? $attribute]));
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('owners', 'document_number')->ignore($ownerId),
            ],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'name' => __('exports.owners.columns.name'),
            'email' => __('exports.owners.columns.email'),
            'is_active' => __('exports.owners.columns.is_active'),
            'phone' => __('exports.owners.columns.phone'),
            'document_number' => __('exports.owners.columns.document_number'),
            'address' => __('exports.owners.columns.address'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return self::CREATED|self::UPDATED
     */
    protected function persist(array $data, ?Model $record): string
    {
        if ($record instanceof Owner) {
            $record->update($data);

            return self::UPDATED;
        }

        Owner::query()->create($data);

        return self::CREATED;
    }
}
