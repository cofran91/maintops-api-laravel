<?php

namespace App\Exporters\Support;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseDataSheet implements FromCollection, ShouldAutoSize, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>  $columns
     */
    public function __construct(
        private readonly array $columns,
        private readonly string $headerColor,
        private readonly string $requiredHeaderColor,
    ) {}

    /**
     * @return Collection<int, array<int, mixed>>
     */
    final public function collection(): Collection
    {
        return $this->records()
            ->map(fn (object $record): array => array_map(
                fn (array $column): mixed => $this->value($record, $column['key']),
                $this->columns,
            ));
    }

    /**
     * @return array<int, string>
     */
    final public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    /**
     * @return array<int|string, mixed>
     */
    final public function styles(Worksheet $sheet): array
    {
        foreach ($this->columns as $index => $column) {
            $cell = Coordinate::stringFromColumnIndex($index + 1).'1';
            $color = $column['required']
                ? $this->requiredHeaderColor
                : $this->headerColor;

            $sheet->getStyle($cell)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $color],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }

    /**
     * @return array<class-string, callable>
     */
    final public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $worksheet = $event->sheet->getDelegate();
                $highestColumn = Coordinate::stringFromColumnIndex(count($this->columns));

                $worksheet->freezePane('A2');
                $worksheet->setAutoFilter('A1:'.$highestColumn.'1');
            },
        ];
    }

    /**
     * @return Collection<int, object>
     */
    abstract protected function records(): Collection;

    abstract protected function value(object $record, string $key): mixed;
}
