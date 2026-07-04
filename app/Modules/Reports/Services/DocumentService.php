<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Engines\DompdfExportEngine;
use App\Modules\Reports\Exceptions\DocumentNotFoundException;
use App\Modules\Reports\Repositories\DocumentBindingRepository;
use App\Modules\Reports\Repositories\HistoryRepository;
use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Services\AuditService;

/**
 * Handles single-record PDF generation via document bindings.
 *
 * All templates are rendered as GrapeJS HTML via Dompdf; legacy block
 * layouts are no longer supported (they were one-shot migrated upstream).
 */
class DocumentService
{
    private DocumentBindingRepository $bindingRepo;
    private StylePresetRepository $stylePresetRepo;
    private HistoryRepository $historyRepo;
    private ExportProviderService $providerService;

    public function __construct()
    {
        $this->bindingRepo = app(DocumentBindingRepository::class);
        $this->stylePresetRepo = app(StylePresetRepository::class);
        $this->historyRepo = app(HistoryRepository::class);
        $this->providerService = app(ExportProviderService::class);
    }

    /**
     * Generate a PDF document for a single record.
     *
     * @param  string $module    Module name
     * @param  string $operation Operation key (e.g. 'invoice', 'receipt')
     * @param  int    $recordId  Primary key of the record
     * @return array  {path, filename, mime}
     * @throws \RuntimeException On failure
     */
    public function generate(string $module, string $operation, int $recordId): array
    {
        // 1. Find binding with template and style joined
        $binding = $this->bindingRepo->findByOperation($module, $operation);
        if ($binding === null) {
            throw new DocumentNotFoundException("Nessun binding trovato per {$module}/{$operation}.");
        }

        $templateHtml = $binding['template_html'] ?? null;
        if (!is_string($templateHtml) || trim($templateHtml) === '') {
            throw new \RuntimeException(
                'Il modello collegato a questo documento non ha un layout salvato. Apri il designer e salva prima di generare il PDF.'
            );
        }

        // 2. Fetch single record via provider
        $sourceKey = $this->findSourceKeyForBinding($binding);

        $record = $this->providerService->fetchSingleRecord($module, $sourceKey, $recordId);
        if ($record === null) {
            throw new DocumentNotFoundException("Record #{$recordId} non trovato nel modulo {$module}.");
        }

        // 3. Build style preset array
        $stylePreset = $this->buildStylePreset($binding);

        // 4. Build metadata
        $user = auth();
        $meta = [
            'title'        => $binding['label'] ?? $binding['template_name'] ?? 'Documento',
            'module'       => $module,
            'source_key'   => $sourceKey,
            'generated_by' => $user['name'] ?? 'Sistema',
            'company_name' => setting('company_name', 'Favilla'),
            'record_id'    => $recordId,
            'operation'    => $operation,
            'style_preset' => $stylePreset,
        ];

        // 5. Generate PDF via Dompdf (HTML template + Smart Components)
        $engine = app(DompdfExportEngine::class);
        $uploadsDir = $this->ensureUploadsDir();
        $timestamp = date('Ymd_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meta['title']);
        $filename = $safeName . '_' . $recordId . '_' . $timestamp . '.pdf';
        $outputPath = $uploadsDir . '/' . $filename;

        // Pass single record as array of one row so loops still work
        $engine->generateFromHtmlTemplate($templateHtml, [$record], $meta, $outputPath);

        // 6. Record in history (expires +7 days)
        try {
            $fileSize = file_exists($outputPath) ? (int) filesize($outputPath) : 0;
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

            $this->historyRepo->create([
                'template_id'     => (int) ($binding['template_id'] ?? 0) ?: null,
                'template_name'   => $binding['template_name'] ?? 'Documento',
                'module'          => $module,
                'source_key'      => $sourceKey,
                'output_format'   => 'pdf',
                'stored_filename' => $filename,
                'file_size'       => $fileSize,
                'row_count'       => 1,
                'filters_used'    => json_encode(['record_id' => $recordId, 'operation' => $operation]),
                'generated_by'    => $user['id'] ?? null,
                'generated_at'    => date('Y-m-d H:i:s'),
                'expires_at'      => $expiresAt,
            ]);
        } catch (\Throwable) {
            // History recording must not abort document generation
        }

        // 7. Audit log
        AuditService::log('document_generated', 'document_binding', (int) $binding['id'], null, [
            'module'    => $module,
            'operation' => $operation,
            'record_id' => $recordId,
        ]);

        return [
            'path'     => $outputPath,
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────

    /**
     * Find source key for a binding by looking at its template's source_key.
     */
    private function findSourceKeyForBinding(array $binding): string
    {
        if (!empty($binding['template_source_key'])) {
            return $binding['template_source_key'];
        }

        // Fallback: use operation as source key
        return $binding['operation'] ?? '';
    }

    /**
     * Build style preset array from binding's joined style fields.
     */
    private function buildStylePreset(array $binding): array
    {
        if (!empty($binding['style_name'])) {
            return [
                'logo_path'           => $binding['style_logo_path'] ?? null,
                'logo_secondary_path' => $binding['style_logo_secondary_path'] ?? null,
                'primary_color'       => $binding['style_primary_color'] ?? '#3b82f6',
                'secondary_color'     => $binding['style_secondary_color'] ?? '#64748b',
                'accent_color'        => $binding['style_accent_color'] ?? '#f97316',
                'header_bg_color'     => $binding['style_header_bg_color'] ?? '#1e293b',
                'header_text_color'   => $binding['style_header_text_color'] ?? '#ffffff',
                'zebra_color'         => $binding['style_zebra_color'] ?? '#f8fafc',
                'font_family'         => $binding['style_font_family'] ?? 'Helvetica, Arial, sans-serif',
                'font_size_base'      => (int) ($binding['style_font_size_base'] ?? 10),
            ];
        }

        // Fallback to DB default
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
                'font_size_base'      => (int) ($default['font_size_base'] ?? 10),
            ];
        }

        return [
            'primary_color'    => '#3b82f6',
            'secondary_color'  => '#64748b',
            'accent_color'     => '#f97316',
            'header_bg_color'  => '#1e293b',
            'header_text_color' => '#ffffff',
            'zebra_color'      => '#f8fafc',
            'font_family'      => 'Helvetica, Arial, sans-serif',
            'font_size_base'   => 10,
        ];
    }

    /**
     * Ensure the reports upload directory exists.
     */
    private function ensureUploadsDir(): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/storage/reports';

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }
}
