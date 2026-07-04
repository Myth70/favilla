<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Services\TemplateService;
use App\Traits\ControllerHelpers;

class ExportController extends Controller
{
    use ControllerHelpers;

    private TemplateService $templateService;

    public function __construct()
    {
        $this->templateService = app(TemplateService::class);
    }

    // ── quickExport — Export data directly (no template) ────────────────────

    public function quickExport(): void
    {
        $clean = $this->cleanGet(['module', 'source_key', 'format', 'sort', 'dir']);

        $module    = $clean['module'] ?? '';
        $sourceKey = $clean['source_key'] ?? '';
        $format    = in_array($clean['format'] ?? '', ['csv', 'excel', 'pdf'], true)
                        ? $clean['format'] : 'csv';

        if (empty($module) || empty($sourceKey)) {
            flash_error(t('reports.flash.export_params_required'));
            header('Location: ' . route('reports.index'));
            exit;
        }

        try {
            $result = $this->templateService->quickExport($module, $sourceKey, $format);
        } catch (\Throwable $e) {
            app_log('error', '[Reports] quickExport error: ' . $e->getMessage());
            flash_error(t('reports.flash.export_error'));
            header('Location: ' . route('reports.index'));
            exit;
        }

        $this->streamFile($result['path'], $result['filename'], $result['mime']);
    }

    // ── generate — Generate report from saved template ──────────────────────

    public function generate(string $id): void
    {
        $id = (int) $id;
        try {
            $result = $this->templateService->generateReport($id);
        } catch (\Throwable $e) {
            app_log('error', '[Reports] generate error: ' . $e->getMessage());
            flash_error(t('reports.flash.generate_error'));
            header('Location: ' . route('reports.templates.index'));
            exit;
        }

        if ($result === null) {
            flash_error(t('reports.flash.tpl_or_source_missing'));
            header('Location: ' . route('reports.templates.index'));
            exit;
        }

        $this->streamFile($result['path'], $result['filename'], $result['mime']);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function streamFile(string $filePath, string $filename, string $mimeType): void
    {
        $safePath = dirname($filePath) . '/' . basename($filePath);

        if (!file_exists($safePath)) {
            http_response_code(404);
            flash_error(t('reports.flash.file_not_found'));
            header('Location: ' . route('reports.index'));
            exit;
        }

        // ISO 27001 A.8.2 — Decrypt if file is encrypted at rest
        $servePath = $safePath;
        $tmpFile   = null;
        try {
            $enc = app(\App\Services\EncryptionService::class);
            if ($enc->isFileEncrypted($safePath)) {
                $tmpFile = $enc->decryptFileToTemp($safePath);
                if ($tmpFile !== null) {
                    $servePath = $tmpFile;
                }
            }
        } catch (\Throwable $e) {
            app_log('error', self::class . ': decrypt export file failed: ' . $e->getMessage());
        }

        header('Content-Type: ' . $mimeType);
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode(basename($filename)));
        header('Content-Length: ' . filesize($servePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($servePath);

        if ($tmpFile !== null && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
        exit;
    }
}
