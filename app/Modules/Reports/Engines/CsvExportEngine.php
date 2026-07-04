<?php

declare(strict_types=1);

namespace App\Modules\Reports\Engines;

use App\Services\CsvExportService;

class CsvExportEngine implements ExportEngineInterface
{
    private string $delimiter;

    public function __construct(string $delimiter = ';')
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Generate a CSV file from data rows and column definitions.
     */
    public function generate(array $rows, array $columns, string $outputPath, string $title = 'Report'): void
    {
        $fp = fopen($outputPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException("Impossibile creare il file CSV: {$outputPath}");
        }

        // UTF-8 BOM for Excel
        fwrite($fp, "\xEF\xBB\xBF");

        // Header row
        $headers = [];
        foreach ($columns as $col) {
            $headers[] = CsvExportService::escapeFormula((string) ($col['label'] ?? $col['name'] ?? ''));
        }
        fputcsv($fp, $headers, $this->delimiter);

        // Data rows
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $name = $col['name'] ?? '';
                $value = $row[$name] ?? '';
                $line[] = $this->formatValue($value, $col);
            }
            fputcsv($fp, $line, $this->delimiter);
        }

        fclose($fp);
    }

    /**
     * Stream CSV directly to the browser (never returns).
     *
     * @param array  $rows     Data rows
     * @param array  $columns  Column definitions
     * @param string $filename Download filename
     * @return never
     */
    public function stream(array $rows, array $columns, string $filename): never
    {
        $safeFilename = preg_replace('/[\r\n"\\\\]/', '_', $filename);

        header('Content-Type: ' . $this->getContentType());
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Header row
        $headers = [];
        foreach ($columns as $col) {
            $headers[] = CsvExportService::escapeFormula((string) ($col['label'] ?? $col['name'] ?? ''));
        }
        fputcsv($out, $headers, $this->delimiter);

        // Data rows
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $name = $col['name'] ?? '';
                $value = $row[$name] ?? '';
                $line[] = $this->formatValue($value, $col);
            }
            fputcsv($out, $line, $this->delimiter);
        }

        fclose($out);
        exit;
    }

    public function generateFromHtmlTemplate(string $htmlTemplate, array $rows, array $meta, string $outputPath): void
    {
        throw new \LogicException('Il formato CSV non supporta i template HTML: usa generate() con le colonne.');
    }

    public function getContentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function getExtension(): string
    {
        return 'csv';
    }

    // ─── Formatting helpers ────────────────────────────────────────

    /**
     * Format a cell value for CSV output (Italian locale).
     */
    private function formatValue(mixed $value, array $col): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $type = $col['type'] ?? 'string';
        $format = $col['format'] ?? null;

        switch ($type) {
            case 'boolean':
                return $this->formatBoolean($value);

            case 'decimal':
                return $this->formatDecimal($value, $format);

            case 'integer':
                return $this->formatInteger($value);

            case 'date':
                return $this->formatDate($value);

            case 'datetime':
                return $this->formatDateTime($value);

            case 'bytes':
                return $this->formatBytes($value);

            default:
                // Free-text cell: guard against CSV/spreadsheet formula injection.
                return CsvExportService::escapeFormula((string) $value);
        }
    }

    private function formatBoolean(mixed $value): string
    {
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['0', 'false', 'no', 'n', ''], true)) {
                return 'No';
            }
            return 'Si';
        }

        return $value ? 'Si' : 'No';
    }

    /**
     * Format decimal with Italian locale: comma for decimal separator, dot for thousands.
     */
    private function formatDecimal(mixed $value, ?string $format): string
    {
        $num = (float) $value;

        switch ($format) {
            case 'currency':
                return number_format($num, 2, ',', '.') . ' EUR';

            case 'percentage':
                return number_format($num, 2, ',', '.') . '%';

            default:
                return number_format($num, 2, ',', '.');
        }
    }

    private function formatInteger(mixed $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }

    /**
     * Format date as dd/mm/yyyy.
     */
    private function formatDate(mixed $value): string
    {
        if (empty($value) || $value === '0000-00-00') {
            return '';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return CsvExportService::escapeFormula((string) $value);
        }

        return date('d/m/Y', $ts);
    }

    /**
     * Format datetime as dd/mm/yyyy HH:mm.
     */
    private function formatDateTime(mixed $value): string
    {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return '';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return CsvExportService::escapeFormula((string) $value);
        }

        return date('d/m/Y H:i', $ts);
    }

    /**
     * Format bytes to human-readable size.
     */
    private function formatBytes(mixed $value): string
    {
        $bytes = (int) $value;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, ($i > 0 ? 1 : 0), ',', '.') . ' ' . $units[$i];
    }

}
