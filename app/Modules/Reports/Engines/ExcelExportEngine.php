<?php

declare(strict_types=1);

namespace App\Modules\Reports\Engines;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExportEngine implements ExportEngineInterface
{
    /**
     * Generate a quick Excel export with headers, zebra stripes, auto-filter, freeze pane.
     */
    public function generate(array $rows, array $columns, string $outputPath, string $title = 'Report'): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));

        if (empty($columns)) {
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);
            return;
        }

        $colCount = count($columns);
        $lastColLetter = Coordinate::stringFromColumnIndex($colCount);

        // ── Header row ──
        foreach ($columns as $colIdx => $col) {
            $cellCol = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($cellCol . '1', $col['label'] ?? $col['name'] ?? '');
        }

        // Header style: bold white text on dark background
        $headerRange = 'A1:' . $lastColLetter . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Data rows ──
        $rowNum = 2;
        foreach ($rows as $row) {
            foreach ($columns as $colIdx => $col) {
                $cellCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                $cellRef = $cellCol . $rowNum;
                $name = $col['name'] ?? '';
                $value = $row[$name] ?? '';
                $this->setCellValue($sheet, $cellRef, $value, $col);
            }

            // Zebra stripe on even rows
            if ($rowNum % 2 === 0) {
                $sheet->getStyle('A' . $rowNum . ':' . $lastColLetter . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF8FAFC'],
                    ],
                ]);
            }

            $rowNum++;
        }

        $lastRow = $rowNum - 1;

        // ── Auto-filter ──
        if ($lastRow >= 1) {
            $sheet->setAutoFilter('A1:' . $lastColLetter . $lastRow);
        }

        // ── Freeze pane (freeze header row) ──
        $sheet->freezePane('A2');

        // ── Auto-size columns ──
        for ($i = 1; $i <= $colCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // ── Borders for all data ──
        if ($lastRow >= 1) {
            $dataRange = 'A1:' . $lastColLetter . $lastRow;
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ]);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }

    public function generateFromHtmlTemplate(string $htmlTemplate, array $rows, array $meta, string $outputPath): void
    {
        throw new \LogicException('Il formato Excel non supporta i template HTML: usa generate() con le colonne.');
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function getExtension(): string
    {
        return 'xlsx';
    }

    // ─── Private helpers ───────────────────────────────────────────

    /**
     * Set cell value with type-appropriate formatting.
     */
    private function setCellValue(Worksheet $sheet, string $cellRef, mixed $value, array $col): void
    {
        $type = $col['type'] ?? 'string';
        $format = $col['format'] ?? null;

        if ($value === null || $value === '') {
            $sheet->setCellValue($cellRef, '');
            return;
        }

        switch ($type) {
            case 'boolean':
                $boolVal = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $sheet->setCellValue($cellRef, $boolVal ? 'Si' : 'No');
                $sheet->getStyle($cellRef)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                break;

            case 'integer':
                $sheet->setCellValue($cellRef, (int) $value);
                $sheet->getStyle($cellRef)->getNumberFormat()
                    ->setFormatCode('#,##0');
                $sheet->getStyle($cellRef)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                break;

            case 'decimal':
                $numVal = (float) $value;
                $sheet->setCellValue($cellRef, $numVal);

                if ($format === 'currency') {
                    $sheet->getStyle($cellRef)->getNumberFormat()
                        ->setFormatCode('#,##0.00 "EUR"');
                } elseif ($format === 'percentage') {
                    $sheet->getStyle($cellRef)->getNumberFormat()
                        ->setFormatCode('#,##0.00"%"');
                } else {
                    $sheet->getStyle($cellRef)->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                $sheet->getStyle($cellRef)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                break;

            case 'date':
                $ts = strtotime((string) $value);
                if ($ts !== false && $value !== '0000-00-00') {
                    $excelDate = Date::PHPToExcel($ts);
                    $sheet->setCellValue($cellRef, $excelDate);
                    $sheet->getStyle($cellRef)->getNumberFormat()
                        ->setFormatCode('DD/MM/YYYY');
                } else {
                    $sheet->setCellValue($cellRef, '');
                }
                $sheet->getStyle($cellRef)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                break;

            case 'datetime':
                $ts = strtotime((string) $value);
                if ($ts !== false && $value !== '0000-00-00 00:00:00') {
                    $excelDate = Date::PHPToExcel($ts);
                    $sheet->setCellValue($cellRef, $excelDate);
                    $sheet->getStyle($cellRef)->getNumberFormat()
                        ->setFormatCode('DD/MM/YYYY HH:MM');
                } else {
                    $sheet->setCellValue($cellRef, '');
                }
                $sheet->getStyle($cellRef)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                break;

            default:
                $sheet->setCellValue($cellRef, (string) $value);
                break;
        }
    }

}
