<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Services\HistoryService;
use App\Modules\Reports\Services\ReportsHistoryQueryService;
use App\Traits\ControllerHelpers;

class HistoryController extends Controller
{
    use ControllerHelpers;

    private HistoryService $historyService;
    private ReportsHistoryQueryService $queryService;

    public function __construct()
    {
        $this->historyService = app(HistoryService::class);
        $this->queryService = app(ReportsHistoryQueryService::class);
    }

    // ── index — List history entries ────────────────────────────────────────

    public function index(): void
    {
        $user      = auth();
        $userId    = (int) $user['id'];
        $adminView = in_array('admin', $user['roles'] ?? [], true) || has_permission('reports.admin');

        $clean   = $this->cleanGet(['q', 'module', 'format', 'date_from', 'date_to', 'sort', 'dir', 'page'], 255);
        $filters = [
            'q'         => $clean['q'] ?? '',
            'module'    => $clean['module'] ?? '',
            'format'    => $clean['format'] ?? '',
            'date_from' => $clean['date_from'] ?? '',
            'date_to'   => $clean['date_to'] ?? '',
            'sort'      => $clean['sort'] ?? 'generated_at',
            'dir'       => $clean['dir'] ?? 'DESC',
        ];
        $page = max(1, (int) ($clean['page'] ?? 1));

        $result = $this->queryService->getPaginatedHistory($filters, $page, $userId, $adminView);

        $data = [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['lastPage'],
            'filters'     => $result['filters'],
            'adminView'   => $result['adminView'],
            'modules'     => $result['modules'],
            'pageTitle'   => t('reports.history.title'),
            'breadcrumbs' => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.history')],
            ],
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Reports/Views/history/partials/history_table', $data);
            return;
        }

        $this->render('Reports/Views/history/index', $data);
    }

    // ── download — Download a history file ──────────────────────────────────

    public function download(string $id): void
    {
        $id = (int) $id;
        $user      = auth();
        $userId    = (int) $user['id'];
        $adminView = in_array('admin', $user['roles'] ?? [], true) || has_permission('reports.admin');

        $entry = $this->queryService->findEntryForUser($id, $userId, $adminView);
        if (!$entry) {
            http_response_code(404);
            exit;
        }

        $filePath = $this->queryService->buildStoredFilePath($entry);

        if ($filePath === null || !file_exists($filePath)) {
            http_response_code(404);
            exit;
        }
        $meta = $this->queryService->buildDownloadMetadata($entry);

        // ISO 27001 A.8.2 — Decrypt if file is encrypted at rest
        $servePath = $filePath;
        $tmpFile   = null;
        try {
            $enc = app(\App\Services\EncryptionService::class);
            if ($enc->isFileEncrypted($filePath)) {
                $tmpFile = $enc->decryptFileToTemp($filePath);
                if ($tmpFile !== null) {
                    $servePath = $tmpFile;
                }
            }
        } catch (\Throwable $e) {
            app_log('error', self::class . ': decrypt report file failed: ' . $e->getMessage());
        }

        header('Content-Type: ' . $meta['mime']);
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($meta['downloadName']));
        header('Content-Length: ' . filesize($servePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // ISO 27001 A.12.4.1 — Audit report download
        try {
            \App\Services\AuditService::log('report_downloaded', 'report_history', $id, null, [
                'template_name' => $entry['template_name'] ?? '',
                'format'        => $entry['output_format'] ?? '',
                'file_size'     => $entry['file_size'] ?? 0,
            ], $userId);
        } catch (\Throwable $e) {
            app_log('error', self::class . ': audit log report download failed: ' . $e->getMessage());
        }

        readfile($servePath);

        if ($tmpFile !== null && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
        exit;
    }

    // ── destroy — Delete history entry + file ───────────────────────────────

    public function destroy(string $id): void
    {
        $id = (int) $id;
        $user      = auth();
        $userId    = (int) $user['id'];
        $adminView = in_array('admin', $user['roles'] ?? [], true) || has_permission('reports.admin');

        $entry = $this->queryService->findEntryForUser($id, $userId, $adminView);
        if (!$entry) {
            if ($this->isAjaxRequest()) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => t('reports.flash.history_not_found')]);
                exit;
            }
            http_response_code(404);
            exit;
        }

        // Delete file from disk
        if (!empty($entry['stored_filename'])) {
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
            $filePath = $basePath . '/storage/reports/' . basename($entry['stored_filename']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $this->queryService->deleteEntry($id);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('reports.flash.history_deleted'), 'warning');
            header('HX-Redirect: ' . route('reports.history.index'));
            return;
        }

        flash_success(t('reports.flash.history_deleted'));
        header('Location: ' . route('reports.history.index'));
        exit;
    }

    // ── cleanup — Remove expired entries (admin only) ───────────────────────

    public function cleanup(): void
    {
        $user = auth();
        if (!in_array('admin', $user['roles'] ?? [], true) && !has_permission('reports.admin')) {
            http_response_code(403);
            exit;
        }

        $result = $this->historyService->cleanupExpired();
        $count = $result['deleted_count'] ?? 0;

        $_SESSION['_flash_success'] = $count > 0
            ? t('reports.flash.cleanup_done', ['count' => $count])
            : t('reports.flash.cleanup_none');

        header('Location: ' . route('reports.history.index'));
        exit;
    }
}
