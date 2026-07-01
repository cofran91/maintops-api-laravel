<?php

namespace App\Exporters;

use App\Exporters\Support\DocumentationSheet;
use DateTimeInterface;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

abstract class BaseExporter implements WithMultipleSheets
{
    use Exportable;

    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public const HEADER_COLOR = '2563EB';

    public const REQUIRED_HEADER_COLOR = 'DC2626';

    public const DOCUMENTATION_HEADER_COLOR = '111827';

    final public function fileName(?DateTimeInterface $downloadedAt = null): string
    {
        $date = ($downloadedAt ?? now())->format('Y-m-d');

        return $this->filePrefix().'-'.$date.'.xlsx';
    }

    /**
     * @return array<int, object>
     */
    final public function sheets(): array
    {
        $columns = $this->columns();

        return [
            $this->dataSheet($columns),
            new DocumentationSheet(
                $columns,
                $this->translationKey(),
                $this->documentationColumnWidths(),
                self::DOCUMENTATION_HEADER_COLOR,
            ),
        ];
    }

    abstract protected function filePrefix(): string;

    abstract protected function translationKey(): string;

    /**
     * @param  array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>  $columns
     */
    abstract protected function dataSheet(array $columns): object;

    /**
     * @return array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>
     */
    abstract protected function columns(): array;

    /**
     * @return array<string, float|int>
     */
    protected function documentationColumnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 16,
            'C' => 72,
            'D' => 34,
        ];
    }
}
