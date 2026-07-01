<?php

namespace App\Exporters\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class DocumentationSheet implements FromArray, ShouldAutoSize, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array{key: string, label: string, required: bool, description: string, example: mixed}>  $columns
     * @param  array<string, float|int>  $columnWidths
     */
    public function __construct(
        private readonly array $columns,
        private readonly string $translationKey,
        private readonly array $columnWidths,
        private readonly string $headerColor,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $column): array => [
                $column['label'],
                $column['required']
                    ? (string) __($this->translationPath('documentation.yes'))
                    : (string) __($this->translationPath('documentation.no')),
                $column['description'],
                $this->exampleValue($column['example']),
            ],
            $this->columns,
        );
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __($this->translationPath('documentation.column')),
            (string) __($this->translationPath('documentation.required')),
            (string) __($this->translationPath('documentation.description')),
            (string) __($this->translationPath('documentation.example')),
        ];
    }

    public function title(): string
    {
        return (string) __($this->translationPath('sheets.documentation'));
    }

    /**
     * @return array<string, float|int>
     */
    public function columnWidths(): array
    {
        return $this->columnWidths;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $this->headerColor],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $lastRow = count($this->columns) + 1;
        $sheet->getStyle('A1:D'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('B2:B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $worksheet = $event->sheet->getDelegate();

                $worksheet->freezePane('A2');
                $worksheet->setAutoFilter('A1:D1');
            },
        ];
    }

    private function translationPath(string $path): string
    {
        return 'exports.'.$this->translationKey.'.'.$path;
    }

    private function exampleValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }
}
