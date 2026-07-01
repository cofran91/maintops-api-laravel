<?php

namespace App\Importers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as LaravelValidator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

abstract class BaseImporter implements WithMultipleSheets, WithStartRow
{
    use Importable;

    protected const CREATED = 'created';

    protected const UPDATED = 'updated';

    private const START_ROW = 2;

    /**
     * @return array{
     *     processed_rows: int,
     *     rows_with_errors: int,
     *     created_records: int,
     *     updated_records: int,
     *     errors: array<int, array{row: int, errors: array<string, array<int, string>>}>
     * }
     */
    final public function import(UploadedFile $file): array
    {
        $result = [
            'processed_rows' => 0,
            'rows_with_errors' => 0,
            'created_records' => 0,
            'updated_records' => 0,
            'errors' => [],
        ];

        foreach ($this->rowsFromFile($file) as $index => $rawRow) {
            $rowNumber = $index + $this->startRow();
            $row = $this->rowValues($rawRow);

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $payload = $this->payloadFromRow($row, $this->columnMap());

            if ($this->isEmptyPayload($payload)) {
                continue;
            }

            $result['processed_rows']++;

            $record = $this->matchingRecord($payload);
            $validator = Validator::make(
                $payload,
                $this->rules($record),
                [],
                $this->attributes(),
            );
            $this->withValidator($validator, $payload, $record);

            if ($validator->fails()) {
                $result['rows_with_errors']++;
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->messages(),
                ];

                continue;
            }

            $action = $this->persist($validator->validated(), $record);

            if ($action === self::UPDATED) {
                $result['updated_records']++;

                continue;
            }

            $result['created_records']++;
        }

        return $result;
    }

    /**
     * @return array<int, self>
     */
    final public function sheets(): array
    {
        return [0 => $this];
    }

    final public function startRow(): int
    {
        return self::START_ROW;
    }

    /**
     * @return array<string, int>
     */
    abstract protected function columnMap(): array;

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>
     */
    abstract protected function payloadFromRow(array $row, array $columnMap): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    abstract protected function matchingRecord(array $payload): ?Model;

    /**
     * @return array<string, array<int, mixed>>
     */
    abstract protected function rules(?Model $record): array;

    /**
     * @return array<string, string>
     */
    abstract protected function attributes(): array;

    /**
     * @param  array<string, mixed>  $data
     * @return self::CREATED|self::UPDATED
     */
    abstract protected function persist(array $data, ?Model $record): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function withValidator(LaravelValidator $validator, array $payload, ?Model $record): void
    {
        //
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function rowsFromFile(UploadedFile $file): array
    {
        /** @var array<int, array<int, array<int, mixed>>> $sheets */
        $sheets = $this->toArray($file);

        return $sheets[0] ?? [];
    }

    /**
     * @param  array<int, mixed>  $rawRow
     * @return array<int, mixed>
     */
    private function rowValues(array $rawRow): array
    {
        $row = [];

        foreach ($rawRow as $column => $value) {
            if (is_int($column)) {
                $row[$column + 1] = $value;
            }
        }

        return $row;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->stringValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isEmptyPayload(array $payload): bool
    {
        foreach ($payload as $value) {
            if (is_array($value)) {
                if ($value !== []) {
                    return false;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    protected function email(mixed $value): ?string
    {
        $email = $this->nullableString($value);

        return $email === null ? null : Str::lower($email);
    }

    protected function code(mixed $value): ?string
    {
        $code = $this->nullableString($value);

        return $code === null ? null : Str::upper(Str::slug($code, '-'));
    }

    protected function boolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        $string = $this->nullableString($value);

        if ($string === null) {
            return null;
        }

        $normalized = Str::lower($string);

        if ($normalized === 'true') {
            return true;
        }

        if ($normalized === 'false') {
            return false;
        }

        return $string;
    }

    protected function integer(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        $string = $this->nullableString($value);

        if ($string === null) {
            return null;
        }

        return preg_match('/^-?\d+$/', $string) === 1 ? (int) $string : $string;
    }

    /**
     * @return array<int, string>
     */
    protected function codeList(mixed $value): array
    {
        return array_map(
            fn (string $code): string => Str::upper(Str::slug($code, '-')),
            $this->list($value),
        );
    }

    /**
     * @return array<int, string>
     */
    protected function emailList(mixed $value): array
    {
        return array_map(
            fn (string $email): string => Str::lower($email),
            $this->list($value),
        );
    }

    /**
     * @return array<int, string>
     */
    protected function list(mixed $value): array
    {
        $string = $this->nullableString($value);

        if ($string === null) {
            return [];
        }

        $items = preg_split('/[,;\n]+/', $string) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $items,
        )));
    }

    protected function nullableString(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return $string === '' ? null : $string;
    }

    protected function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
