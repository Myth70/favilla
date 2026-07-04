<?php

declare(strict_types=1);

namespace App\Modules\Files\Controllers;

use App\Core\Controller;
use App\Modules\Files\Services\FilesService;
use App\Services\CsvExportService;
use App\Traits\ControllerHelpers;

class AdminFilesController extends Controller
{
    use ControllerHelpers;

    private FilesService $service;

    public function __construct()
    {
        $this->service = app(FilesService::class);
    }

    // ── admin index ────────────────────────────────────────────────────────

    public function index(): void
    {
        $clean = $this->cleanGet(['search', 'user_id', 'mime_group', 'visibility', 'date_from', 'date_to', 'sort', 'dir', 'page']);
        $filters = [
            'search'     => $clean['search']     ?? '',
            'user_id'    => $clean['user_id']    ?? '',
            'mime_group' => $clean['mime_group'] ?? '',
            'visibility' => $clean['visibility'] ?? '',
            'date_from'  => $clean['date_from']  ?? '',
            'date_to'    => $clean['date_to']    ?? '',
            'sort'       => $clean['sort']        ?? 'created_at',
            'dir'        => $clean['dir']         ?? 'DESC',
        ];
        $page = max(1, (int) ($clean['page'] ?? 1));

        $result = $this->service->listPaginated($filters, 0, true, $page, 20);
        $stats  = $this->service->adminStats();

        $users = $this->service->listUsers();

        $data = array_merge($result, [
            'total_pages' => $result['lastPage'],
            'filters'     => $filters,
            'stats'       => $stats,
            'users'       => $users,
            'pageTitle'   => t('files.admin.title'),
            'breadcrumbs' => [
                ['label' => t('files.title'), 'route' => 'files.index'],
                ['label' => t('files.breadcrumb.admin')],
            ],
        ]);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Files/Views/admin/partials/table', $data);
            return;
        }

        $this->render('Files/Views/admin/index', $data);
    }

    // ── trash ──────────────────────────────────────────────────────────────

    public function trash(): void
    {
        $cleanTrash = $this->cleanGet(['search', 'page']);
        $filters = [
            'search' => $cleanTrash['search'] ?? '',
        ];
        $page   = max(1, (int) ($cleanTrash['page'] ?? 1));
        $result = $this->service->listDeleted($filters, $page, 20);

        $data = array_merge($result, [
            'total_pages' => $result['lastPage'],
            'filters'     => $filters,
            'pageTitle'   => t('files.admin.trash_title'),
            'breadcrumbs' => [
                ['label' => t('files.title'), 'route' => 'files.index'],
                ['label' => t('files.breadcrumb.admin'), 'route' => 'files.admin.index'],
                ['label' => t('files.breadcrumb.trash')],
            ],
        ]);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Files/Views/admin/partials/trash_table', $data);
            return;
        }

        $this->render('Files/Views/admin/trash', $data);
    }

    // ── stats widget (HTMX auto-refresh) ───────────────────────────────────

    public function statsWidget(): void
    {
        $stats = $this->service->adminStats();
        $this->renderPartial('Files/Views/admin/partials/stats_widget', ['stats' => $stats]);
    }

    // ── CSV export ─────────────────────────────────────────────────────────

    public function export(): void
    {
        $cleanExport = $this->cleanGet(['search', 'user_id', 'mime_group', 'visibility', 'date_from', 'date_to', 'sort', 'dir']);
        $filters = [
            'search'     => $cleanExport['search']     ?? '',
            'user_id'    => $cleanExport['user_id']    ?? '',
            'mime_group' => $cleanExport['mime_group'] ?? '',
            'visibility' => $cleanExport['visibility'] ?? '',
            'date_from'  => $cleanExport['date_from']  ?? '',
            'date_to'    => $cleanExport['date_to']    ?? '',
            'sort'       => $cleanExport['sort']        ?? 'created_at',
            'dir'        => $cleanExport['dir']         ?? 'DESC',
        ];

        $result = $this->service->listAllFiltered($filters);
        $rows   = array_map(function (array $file): array {
            return [
                t('files.export.id')            => $file['id'],
                t('files.export.original_name') => $file['original_name'],
                t('files.export.extension')     => $file['extension'],
                t('files.export.mime')          => $file['mime_type'],
                t('files.export.size')          => $file['size_bytes'],
                t('files.export.folder')        => $file['folder'],
                t('files.export.visibility')    => $file['visibility'],
                t('files.export.uploaded_by')   => $file['uploader_name'] ?? '',
                t('files.export.uploaded_at')   => $file['created_at'],
                t('files.export.deleted_at')    => $file['deleted_at'] ?? '',
            ];
        }, $result['items']);

        CsvExportService::stream($rows, 'files_export_' . date('Ymd') . '.csv');
    }

    // ── bulk soft-delete ───────────────────────────────────────────────────

    public function bulkDelete(): void
    {
        $ids   = array_filter(array_map('intval', $_POST['ids'] ?? []));
        $count = $this->service->bulkSoftDelete($ids);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.bulk_trashed', ['count' => $count]), 'warning');
            header('HX-Redirect: ' . route('files.admin.index'));
            return;
        }

        flash_success(t('files.flash.bulk_trashed', ['count' => $count]));
        header('Location: ' . route('files.admin.index'));
        exit;
    }

    // ── bulk purge (hard-delete) ───────────────────────────────────────────

    public function bulkPurge(): void
    {
        $ids   = array_filter(array_map('intval', $_POST['ids'] ?? []));
        $count = $this->service->bulkPurge($ids);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.bulk_purged', ['count' => $count]), 'danger');
            header('HX-Redirect: ' . route('files.admin.trash'));
            return;
        }

        flash_success(t('files.flash.bulk_purged', ['count' => $count]));
        header('Location: ' . route('files.admin.trash'));
        exit;
    }

    // ── restore single ─────────────────────────────────────────────────────

    public function restore(string $id): void
    {
        $this->service->restore((int) $id);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.restored'));
            header('HX-Redirect: ' . route('files.admin.trash'));
            return;
        }

        flash_success(t('files.flash.restored'));
        header('Location: ' . route('files.admin.trash'));
        exit;
    }

    // ── purge single (hard-delete) ─────────────────────────────────────────

    public function purge(string $id): void
    {
        $this->service->purge((int) $id);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.purged'), 'danger');
            header('HX-Redirect: ' . route('files.admin.trash'));
            return;
        }

        flash_success(t('files.flash.purged'));
        header('Location: ' . route('files.admin.trash'));
        exit;
    }
}
