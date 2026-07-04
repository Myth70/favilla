<?php

declare(strict_types=1);

namespace App\Modules\Reports\Engines;

interface ExportEngineInterface
{
    /**
     * Generate a file from data rows + column config.
     *
     * @param array  $rows       Flat array of associative rows
     * @param array  $columns    Column definitions [{name, label, type, format?, ...}]
     * @param string $outputPath Absolute path for the output file
     * @param string $title      Report title
     */
    public function generate(array $rows, array $columns, string $outputPath, string $title = 'Report'): void;

    /**
     * Render an HTML/GrapeJS template (with `{{ }}` placeholders) to the output file.
     *
     * Only layout-oriented engines (PDF) support this. Column-based engines
     * (CSV, Excel) MUST throw \LogicException — callers select the engine by
     * format and should never route an HTML template to a tabular engine.
     *
     * @param string $htmlTemplate HTML template with `{{ }}` placeholders
     * @param array  $rows         Data rows to iterate/substitute
     * @param array  $meta         Metadata for header/footer/style preset
     * @param string $outputPath   Absolute path for the output file
     */
    public function generateFromHtmlTemplate(string $htmlTemplate, array $rows, array $meta, string $outputPath): void;

    /** MIME content type for the generated file. */
    public function getContentType(): string;

    /** File extension without dot (e.g. 'csv', 'xlsx', 'pdf'). */
    public function getExtension(): string;
}
