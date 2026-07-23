<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Generic spreadsheet export for every report.
 *
 * All five reports produce the same shape — a collection of flat associative
 * rows — so one export class serves them all rather than five near-identical
 * ones. It drives both the CSV and XLSX downloads.
 */
class ReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  key => column heading
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $columns,
        private readonly string $title,
    ) {}

    /**
     * @return Collection<int, array<int, mixed>>
     */
    public function collection(): Collection
    {
        // Project each row onto the configured columns, in order, so the data
        // always lines up with the headings.
        return $this->rows->map(
            fn (array $row): array => array_map(
                fn (string $key): mixed => $row[$key] ?? '',
                array_keys($this->columns)
            )
        );
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function title(): string
    {
        // Excel sheet names are capped at 31 characters.
        return mb_substr($this->title, 0, 31);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '1A56DB'],
                ],
            ],
        ];
    }
}
