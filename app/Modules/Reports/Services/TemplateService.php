<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Engines\CsvExportEngine;
use App\Modules\Reports\Engines\DompdfExportEngine;
use App\Modules\Reports\Engines\ExcelExportEngine;
use App\Modules\Reports\Engines\ExportEngineInterface;
use App\Modules\Reports\Repositories\HistoryRepository;
use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Modules\Reports\Repositories\TemplateRepository;
use App\Services\AuditService;

/**
 * Orchestrates report generation from saved templates.
 */
class TemplateService
{
    private TemplateRepository $templateRepo;
    private StylePresetRepository $stylePresetRepo;
    private HistoryRepository $historyRepo;
    private ExportProviderService $providerService;

    public function __construct()
    {
        $this->templateRepo = app(TemplateRepository::class);
        $this->stylePresetRepo = app(StylePresetRepository::class);
        $this->historyRepo = app(HistoryRepository::class);
        $this->providerService = app(ExportProviderService::class);
    }

    /**
     * Generate a report from a saved template.
     *
     * @param int   $templateId       Template ID
     * @param array $overrideFilters  Optional filters to override template defaults
     * @return array|null {path, filename, mime} or null on failure
     */
    public function generateReport(int $templateId, array $overrideFilters = []): ?array
    {
        // 1. Load template with style
        $template = $this->templateRepo->findWithStyle($templateId);
        if ($template === null) {
            return null;
        }

        $module = $template['module'];
        $sourceKey = $template['source_key'];
        $format = $template['output_format'];
        $templateHtml = $template['template_html'] ?? null;
        $filtersConfig = json_decode($template['filters_config'] ?? '{}', true) ?: [];
        $sortingConfig = json_decode($template['sorting_config'] ?? '{}', true) ?: [];
        $maxRows = (int) ($template['max_rows'] ?? 10000);

        // 2. Merge filters: template defaults + overrides
        $filters = array_merge($filtersConfig, $overrideFilters);

        // 3. Get sort config (support both 'sort_by'/'sort_dir' and 'field'/'dir' keys)
        $sortBy = $sortingConfig['sort_by'] ?? $sortingConfig['field'] ?? 'created_at';
        $sortDir = $sortingConfig['sort_dir'] ?? $sortingConfig['dir'] ?? 'DESC';

        // 4. Get current user
        $user = auth();

        // 5. Fetch data from provider (permission check is internal)
        try {
            $rows = $this->providerService->fetchData(
                $module,
                $sourceKey,
                $filters,
                $sortBy,
                $sortDir,
                $maxRows
            );
        } catch (\RuntimeException) {
            return null;
        }

        // 6. Build style preset array
        $stylePreset = $this->buildStylePreset($template);

        // 7. Build metadata
        $meta = [
            'title'         => $template['name'] ?? 'Report',
            'module'        => $module,
            'source_key'    => $sourceKey,
            'generated_by'  => $user['name'] ?? 'Sistema',
            'company_name'  => setting('company_name', 'Favilla'),
            'filters'       => $filters,
            'source_fields' => $this->providerService->getSourceFields($module, $sourceKey) ?? [],
        ];

        // 8. Get the appropriate engine
        $engine = $this->getEngine($format);

        // 9. Generate file
        $uploadsDir = $this->ensureUploadsDir();
        $timestamp = date('Ymd_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['name'] ?? 'report');
        $filename = $safeName . '_' . $timestamp . '.' . $engine->getExtension();
        $outputPath = $uploadsDir . '/' . $filename;

        // Unified render pipeline.
        // PDF: requires template_html (GrapeJS) — fail loudly if missing.
        // Excel/CSV: column-based export driven by the source fields.
        if ($format === 'pdf') {
            if (!$templateHtml) {
                throw new \RuntimeException(
                    'Il modello non ha un layout salvato. Apri il designer, disegna il report e salva prima di generare il PDF.'
                );
            }
            $meta['style_preset'] = $stylePreset;
            $engine->generateFromHtmlTemplate($templateHtml, $rows, $meta, $outputPath);
        } else {
            $columns = $meta['source_fields'];
            if ($format === 'csv') {
                $columns = $this->buildCsvColumns($columns);
            }
            $engine->generate($rows, $columns, $outputPath, $meta['title']);
        }

        // 10. Record in history (non-blocking)
        try {
            $fileSize = file_exists($outputPath) ? (int) filesize($outputPath) : 0;
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

            $this->historyRepo->create([
                'template_id'     => $templateId,
                'template_name'   => $template['name'] ?? 'Report',
                'module'          => $module,
                'source_key'      => $sourceKey,
                'output_format'   => $format,
                'stored_filename' => $filename,
                'file_size'       => $fileSize,
                'row_count'       => count($rows),
                'filters_used'    => !empty($filters) ? json_encode($filters, JSON_UNESCAPED_UNICODE) : null,
                'generated_by'    => $user['id'] ?? null,
                'generated_at'    => date('Y-m-d H:i:s'),
                'expires_at'      => $expiresAt,
            ]);
        } catch (\Throwable) {
            // History recording must not abort report generation
        }

        // 11. Audit log
        AuditService::log('report_generated', 'report_template', $templateId, null, [
            'template_name' => $template['name'],
            'format'        => $format,
            'row_count'     => count($rows),
        ]);

        // 12. ISO 27001 A.8.2 — Encrypt report file at rest
        if (setting('reports_encrypt_at_rest', false)) {
            try {
                $enc = app(\App\Services\EncryptionService::class);
                $enc->encryptFile($outputPath);
            } catch (\Throwable) {
                // Encryption failure must not prevent report delivery
            }
        }

        return [
            'path'     => $outputPath,
            'filename' => $filename,
            'mime'     => $engine->getContentType(),
        ];
    }

    /**
     * Preview data from a source (limited rows, no file generation).
     *
     * @return array|null {rows, columns, total} or null on failure
     */
    public function previewData(array $template, int $limit = 25): ?array
    {
        $module = $template['module'] ?? '';
        $sourceKey = $template['source_key'] ?? '';

        $fields = $this->providerService->getSourceFields($module, $sourceKey);
        if ($fields === null) {
            return null;
        }

        $filtersConfig = json_decode($template['filters_config'] ?? '{}', true) ?: [];

        $rows = $this->providerService->fetchData(
            $module,
            $sourceKey,
            $filtersConfig,
            'created_at',
            'DESC',
            $limit
        );

        return [
            'rows'    => $rows,
            'columns' => $fields,
            'total'   => count($rows),
        ];
    }

    /**
     * Quick export: generate a file without a saved template.
     *
     * @return array {path, filename, mime}
     */
    public function quickExport(string $module, string $sourceKey, string $format, array $filters = []): array
    {
        // Validate format
        if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
            throw new \InvalidArgumentException("Formato non supportato: {$format}");
        }

        // Fetch source fields as columns
        $columns = $this->providerService->getSourceFields($module, $sourceKey);
        if ($columns === null) {
            throw new \RuntimeException("Sorgente dati {$module}/{$sourceKey} non trovata.");
        }

        // Fetch data
        $rows = $this->providerService->fetchData($module, $sourceKey, $filters);

        // Get engine
        $engine = $this->getEngine($format);

        // Generate file
        $uploadsDir = $this->ensureUploadsDir();
        $timestamp = date('Ymd_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $module . '_' . $sourceKey);
        $filename = 'export_' . $safeName . '_' . $timestamp . '.' . $engine->getExtension();
        $outputPath = $uploadsDir . '/' . $filename;

        $title = $module . ' — ' . $sourceKey;
        $engine->generate($rows, $columns, $outputPath, $title);

        // Record in history
        try {
            $fileSize = file_exists($outputPath) ? (int) filesize($outputPath) : 0;
            $user = auth();

            $this->historyRepo->create([
                'template_id'     => null,
                'template_name'   => 'Export rapido: ' . $title,
                'module'          => $module,
                'source_key'      => $sourceKey,
                'output_format'   => $format,
                'stored_filename' => $filename,
                'file_size'       => $fileSize,
                'row_count'       => count($rows),
                'filters_used'    => !empty($filters) ? json_encode($filters, JSON_UNESCAPED_UNICODE) : null,
                'generated_by'    => $user['id'] ?? null,
                'generated_at'    => date('Y-m-d H:i:s'),
                'expires_at'      => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return [
            'path'     => $outputPath,
            'filename' => $filename,
            'mime'     => $engine->getContentType(),
            'rows'     => $rows,
            'columns'  => $columns,
        ];
    }

    /**
     * Build CSV-compatible columns. Ensures each column has
     * 'name', 'label', 'type', 'format'.
     */
    public function buildCsvColumns(array $columns): array
    {
        $csvColumns = [];
        foreach ($columns as $col) {
            $csvColumns[] = [
                'name'   => $col['name'] ?? '',
                'label'  => $col['label'] ?? $col['name'] ?? '',
                'type'   => $col['type'] ?? 'string',
                'format' => $col['format'] ?? null,
            ];
        }

        return $csvColumns;
    }

    /**
     * Ensure the reports upload directory exists and return its path.
     */
    public function ensureUploadsDir(): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/storage/reports';

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    /**
     * Return default style preset values.
     */
    public function defaultStylePreset(): array
    {
        return [
            'logo_path'           => null,
            'logo_secondary_path' => null,
            'primary_color'       => '#3b82f6',
            'secondary_color'     => '#64748b',
            'accent_color'        => '#f97316',
            'header_bg_color'     => '#1e293b',
            'header_text_color'   => '#ffffff',
            'zebra_color'         => '#f8fafc',
            'font_family'         => 'Helvetica, Arial, sans-serif',
            'font_size_base'      => 9,
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────

    /**
     * Build a style preset array from the template's joined style fields.
     */
    private function buildStylePreset(array $template): array
    {
        // If template has a style preset joined, use those values
        $hasStyle = !empty($template['style_name']);

        if ($hasStyle) {
            return [
                'logo_path'           => $template['style_logo_path'] ?? null,
                'logo_secondary_path' => $template['style_logo_secondary_path'] ?? null,
                'primary_color'       => $template['style_primary_color'] ?? '#3b82f6',
                'secondary_color'     => $template['style_secondary_color'] ?? '#64748b',
                'accent_color'        => $template['style_accent_color'] ?? '#f97316',
                'header_bg_color'     => $template['style_header_bg_color'] ?? '#1e293b',
                'header_text_color'   => $template['style_header_text_color'] ?? '#ffffff',
                'zebra_color'         => $template['style_zebra_color'] ?? '#f8fafc',
                'font_family'         => $template['style_font_family'] ?? 'Helvetica, Arial, sans-serif',
                'font_size_base'      => (int) ($template['style_font_size_base'] ?? 9),
            ];
        }

        // Fallback: try DB default or hard-coded defaults
        $default = $this->stylePresetRepo->getDefault();
        if ($default !== null) {
            return [
                'logo_path'           => $default['logo_path'] ?? null,
                'logo_secondary_path' => $default['logo_secondary_path'] ?? null,
                'primary_color'       => $default['primary_color'] ?? '#3b82f6',
                'secondary_color'     => $default['secondary_color'] ?? '#64748b',
                'accent_color'        => $default['accent_color'] ?? '#f97316',
                'header_bg_color'     => $default['header_bg_color'] ?? '#1e293b',
                'header_text_color'   => $default['header_text_color'] ?? '#ffffff',
                'zebra_color'         => $default['zebra_color'] ?? '#f8fafc',
                'font_family'         => $default['font_family'] ?? 'Helvetica, Arial, sans-serif',
                'font_size_base'      => (int) ($default['font_size_base'] ?? 9),
            ];
        }

        return $this->defaultStylePreset();
    }

    /**
     * Get the appropriate export engine for the given format.
     * NEW: Uses Dompdf for PDF by default (better CSS support, HTML templates).
     */
    private function getEngine(string $format): ExportEngineInterface
    {
        return match ($format) {
            'csv'   => app(CsvExportEngine::class),
            'excel' => app(ExcelExportEngine::class),
            'pdf'   => app(DompdfExportEngine::class),
            default => throw new \InvalidArgumentException("Formato di export non supportato: {$format}"),
        };
    }
}
