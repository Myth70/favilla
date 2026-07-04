<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Streams a CSV download to the browser.
 *
 * Usage:
 *   CsvExportService::stream($rows, 'export_' . date('Ymd') . '.csv');
 *
 * The first row's keys are used as column headers.
 * Outputs UTF-8 BOM so Excel opens the file correctly on Italian locales.
 */
class CsvExportService
{
    /**
     * Set HTTP headers and stream rows as a CSV file.
     *
     * @param array  $rows       Array of associative arrays. Keys of the first element become headers.
     * @param string $filename   Suggested download filename (e.g. 'log_audit_20260307.csv').
     * @param string $delimiter  Column separator — default ';' (Excel-friendly on Italian Windows).
     */
    public static function stream(array $rows, string $filename, string $delimiter = ';'): never
    {
        // Sanitizza il filename e usa RFC 5987 per preservare UTF-8 senza CRLF injection
        $asciiName = preg_replace('/[^A-Za-z0-9._\-]/', '_', $filename) ?? 'export.csv';
        $asciiName = substr($asciiName, 0, 100);
        $utf8Name  = rawurlencode($filename);

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$asciiName}\"; filename*=UTF-8''{$utf8Name}");
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // UTF-8 BOM — required for Excel to recognise the encoding automatically
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]), $delimiter);
            foreach ($rows as $row) {
                fputcsv($out, self::sanitizeRow($row), $delimiter);
            }
        }
        fclose($out);
        exit;
    }

    /**
     * Prefix cells that begin with a formula-trigger character so that
     * spreadsheet applications treat them as plain text.
     *
     * Characters: = + - @ (Excel/LibreOffice formula starters)
     * A leading single-quote is the universally recognised "force text" prefix.
     */
    private static function sanitizeRow(array $row): array
    {
        return array_map(static function (mixed $value): mixed {
            return is_string($value) ? self::escapeFormula($value) : $value;
        }, $row);
    }

    /**
     * Force a single string cell to be treated as text if it begins with a
     * spreadsheet formula-trigger character (`= + - @`, TAB or CR). A leading
     * single-quote is the universally recognised "force text" prefix.
     *
     * Only call this on free-text cells: applying it to formatted numerics
     * would wrongly quote legitimate negative values.
     */
    public static function escapeFormula(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
