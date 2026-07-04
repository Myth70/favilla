<?php

declare(strict_types=1);

namespace App\Modules\Files\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Files\Services\FilesService;
use App\Traits\ControllerHelpers;

class FilesController extends Controller
{
    use ControllerHelpers;

    private FilesService $service;

    public function __construct()
    {
        $this->service = app(FilesService::class);
    }

    // ── index ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        $clean = $this->cleanGet(['search', 'folder', 'mime_group', 'visibility', 'sort', 'dir', 'view', 'page', 'scope']);
        $filters = [
            'search'     => $clean['search']     ?? '',
            'folder'     => $clean['folder']     ?? '',
            'mime_group' => $clean['mime_group'] ?? '',
            'visibility' => $clean['visibility'] ?? '',
            'sort'       => $clean['sort']        ?? 'created_at',
            'dir'        => $clean['dir']         ?? 'DESC',
            'scope'      => in_array($clean['scope'] ?? '', ['recent', 'mine', 'shared'], true) ? $clean['scope'] : 'recent',
        ];
        $page     = max(1, (int) ($clean['page'] ?? 1));
        $viewMode = in_array($clean['view'] ?? '', ['grid', 'list'], true) ? $clean['view'] : 'grid';

        $result       = $this->service->listPaginated($filters, $userId, $admin, $page);
        $folders      = $this->service->listFolders($userId, $admin);
        $folderCounts = $this->service->folderCounts($userId, $admin);
        $fileStats    = $this->service->getUserStats($userId);

        // Profile data for hero section
        $userProfile = [
            'name'   => $_SESSION['user_name'] ?? '',
            'email'  => $_SESSION['user_email'] ?? '',
            'avatar' => $_SESSION['user_avatar'] ?? null,
        ];

        $data = array_merge($result, [
            'total_pages'  => $result['lastPage'],
            'filters'      => $filters,
            'viewMode'     => $viewMode,
            'folders'      => $folders,
            'folderCounts' => $folderCounts,
            'fileStats'    => $fileStats,
            'userProfile'  => $userProfile,
            'pageTitle' => t('files.title'),
            'breadcrumbs' => [
                ['label' => t('files.title')],
            ],
        ]);

        if ($this->isHtmxRequest()) {
            $partial = $viewMode === 'list'
                ? 'Files/Views/partials/list_table'
                : 'Files/Views/partials/grid';
            $this->renderPartial($partial, $data);
            return;
        }

        $this->render('Files/Views/index', $data);
    }

    // ── picker (GET — HTMX) ────────────────────────────────────────────────

    public function picker(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        $cleanPicker = $this->cleanGet(['q', 'mime', 'page'], 255);
        $filters = [
            'search' => trim($cleanPicker['q'] ?? ''),
            'sort'   => 'created_at',
            'dir'    => 'DESC',
        ];
        if (!empty($cleanPicker['mime'])) {
            $filters['mime_group'] = $cleanPicker['mime'];
        }

        $page   = max(1, (int) ($cleanPicker['page'] ?? 1));
        $result = $this->service->listPaginated($filters, $userId, $admin, $page, 18);

        $this->renderPartial('Files/Views/partials/picker_grid', [
            'items'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'totalPages' => $result['lastPage'],
            'q'          => $filters['search'],
            'mimeFilter' => $cleanPicker['mime'] ?? '',
        ]);
    }

    // ── upload (GET) ───────────────────────────────────────────────────────

    public function upload(): void
    {
        $user    = auth();
        $userId  = (int) $user['id'];
        $folders = $this->service->listFolders($userId, has_permission('files.admin'));
        $errors  = $_SESSION['_errors'] ?? [];
        $old     = $_SESSION['_old']    ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Files/Views/upload', [
            'folders'      => $folders,
            'errors'       => $errors,
            'old'          => $old,
            'allowedMimes' => FilesService::ALLOWED_MIMES,
            'maxBytes'     => FilesService::MAX_BYTES,
            'acceptAttr'   => FilesService::ACCEPT_ATTR,
            'pageTitle'    => t('files.upload_title'),
            'breadcrumbs'  => [
                ['label' => t('files.title'), 'route' => 'files.index'],
                ['label' => t('files.breadcrumb.upload')],
            ],
        ]);
    }

    // ── store (POST) ───────────────────────────────────────────────────────

    public function store(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];

        // Validate
        $errors = [];
        if (empty($_FILES['file']['name'])) {
            $errors['file'] = [t('files.flash.select_file')];
        }
        if (!empty($_POST['description']) && mb_strlen($_POST['description']) > 500) {
            $errors['description'] = [t('files.flash.desc_too_long')];
        }

        if ($errors) {
            $this->flashErrors($errors, $_POST, 'files.upload');
            return;
        }

        try {
            $meta = $this->cleanPost(['description', 'tags', 'folder', 'visibility']);
            $id   = $this->service->store($_FILES['file'], $meta, $userId);
            $file = $this->service->findWithOwner($id);

            if ($this->isHtmxRequest()) {
                $this->hxToast(t('files.flash.uploaded_named', ['name' => e($file['original_name'])]));
                header('HX-Redirect: ' . route('files.show', ['id' => $id]));
                return;
            }

            flash_success(t('files.flash.uploaded'));
            header('Location: ' . route('files.show', ['id' => $id]));
            exit;
        } catch (\RuntimeException $e) {
            $errors['file'] = [$e->getMessage()];
            $this->flashErrors($errors, $_POST, 'files.upload');
        }
    }

    // ── show ───────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $file = $this->service->findActiveWithOwner((int) $id);
        if (!$file) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }

        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');
        $owner  = (int) $file['created_by'] === $userId;

        if (!$this->canAccess($file, $userId, $admin)) {
            http_response_code(403);
            $this->render('errors/403', []);
            return;
        }

        $previewType = $this->resolvePreviewType($file['mime_type']);
        // Streaming via route con ACL: uploads/files non è più servita da Apache.
        $fileUrl     = route('files.preview', ['id' => (int) $file['id']]);

        // Load text preview content in the controller (not the view)
        $textPreview = null;
        if ($previewType === 'text') {
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
            $physPath = $basePath . '/public/uploads/' . $file['directory'] . '/' . basename($file['stored_name']);
            if (file_exists($physPath) && $file['size_bytes'] < 102400) {
                $textPreview = file_get_contents($physPath, false, null, 0, 102400);
            }
        }

        $canManage = $admin || $owner;
        $canShare = $canManage && has_permission('files.share');

        $this->render('Files/Views/show', [
            'fileRecord'  => $file,
            'previewType' => $previewType,
            'fileUrl'     => $fileUrl,
            'textPreview' => $textPreview,
            'canEdit'     => $canManage,
            'canDelete'   => $canManage,
            'canManage'   => $canManage,
            'canShare'    => $canShare,
            'versions'    => $canManage ? $this->service->listVersions((int) $id) : [],
            'shares'      => $canShare ? $this->service->listShares((int) $id) : [],
            'shareUsers'  => $canShare ? $this->service->listUsers() : [],
            'shareRoles'  => $canShare ? $this->service->listRoles() : [],
            'sizeHr'      => FilesService::humanSize((int) $file['size_bytes']),
            'pageTitle'   => e($file['original_name']),
            'breadcrumbs' => [
                ['label' => t('files.title'), 'route' => 'files.index'],
                ['label' => e($file['original_name'])],
            ],
        ]);
    }

    public function snapshotVersion(string $id): void
    {
        $fileId = (int) $id;
        $user = auth();
        $userId = (int) $user['id'];
        $file = $this->service->findActiveWithOwner($fileId);

        if (!$file) {
            flash_error(t('files.flash.not_found'));
            header('Location: ' . route('files.index'));
            exit;
        }

        if (!$this->isOwnerOrAdmin($file, $userId)) {
            flash_error(t('files.flash.snapshot_denied'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $versionId = $this->service->snapshotVersion($fileId, $file, $userId);
        flash_success(t('files.flash.snapshot_created', ['id' => $versionId]));
        header('Location: ' . route('files.show', ['id' => $fileId]));
        exit;
    }

    public function restoreVersion(string $id): void
    {
        $fileId = (int) $id;
        $user = auth();
        $userId = (int) $user['id'];
        $file = $this->service->findActiveWithOwner($fileId);

        if (!$file) {
            flash_error(t('files.flash.not_found'));
            header('Location: ' . route('files.index'));
            exit;
        }

        if (!$this->isOwnerOrAdmin($file, $userId)) {
            flash_error(t('files.flash.restore_denied'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $clean = $this->cleanPost(['version_no']);
        $versionNo = (int) ($clean['version_no'] ?? 0);
        if ($versionNo <= 0) {
            flash_error(t('files.flash.version_invalid'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        try {
            $this->service->restoreVersion($fileId, $versionNo, $userId);
            flash_success(t('files.flash.version_restored'));
        } catch (\Throwable $e) {
            app_log('error', '[Files] restoreVersion error: ' . $e->getMessage());
            flash_error(t('files.flash.version_error'));
        }

        header('Location: ' . route('files.show', ['id' => $fileId]));
        exit;
    }

    public function shareUser(string $id): void
    {
        $this->handleShare($id, 'user');
    }

    public function shareRole(string $id): void
    {
        $this->handleShare($id, 'role');
    }

    public function revokeShare(string $id): void
    {
        $fileId = (int) $id;
        $user = auth();
        $userId = (int) $user['id'];
        $file = $this->service->findActiveWithOwner($fileId);

        if (!$file) {
            flash_error(t('files.flash.not_found'));
            header('Location: ' . route('files.index'));
            exit;
        }

        if (!$this->isOwnerOrAdmin($file, $userId) || !has_permission('files.share')) {
            flash_error(t('files.flash.revoke_denied'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $clean = $this->cleanPost(['target_type', 'target_id']);
        $targetType = (string) ($clean['target_type'] ?? '');
        $targetId = (int) ($clean['target_id'] ?? 0);

        if (!in_array($targetType, ['user', 'role'], true) || $targetId <= 0) {
            flash_error(t('files.flash.revoke_target_invalid'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $ok = $this->service->revokeShare($fileId, $targetType, $targetId);
        flash_success($ok ? t('files.flash.share_revoked') : t('files.flash.share_not_revoked'));
        header('Location: ' . route('files.show', ['id' => $fileId]));
        exit;
    }

    // ── download ───────────────────────────────────────────────────────────

    public function download(string $id): void
    {
        $file = $this->service->findActiveWithOwner((int) $id);
        if (!$file) {
            http_response_code(404);
            exit;
        }

        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        if (!$this->canAccess($file, $userId, $admin)) {
            http_response_code(403);
            exit;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $filePath = $basePath . '/public/uploads/' . $file['directory'] . '/' . basename($file['stored_name']);

        if (!file_exists($filePath)) {
            http_response_code(404);
            exit;
        }

        // ISO 27001 A.12.2 — Verify file integrity before serving
        $storedChecksum = $file['checksum_sha256'] ?? null;
        if ($storedChecksum) {
            $currentChecksum = hash_file('sha256', $filePath);
            if (!hash_equals($storedChecksum, $currentChecksum)) {
                // Log integrity failure as security incident
                try {
                    $incident = app(\App\Services\SecurityIncidentService::class);
                    $incident->recordIncident('file_integrity_failure', 'high', json_encode([
                        'file_id'  => (int) $id,
                        'filename' => $file['original_name'],
                        'stored'   => $storedChecksum,
                        'computed' => $currentChecksum,
                    ], JSON_THROW_ON_ERROR), \App\Support\ClientIp::resolve());
                } catch (\Throwable) {
                    // Never block download for logging failure
                }

                header('X-Integrity-Status: FAILED');
            } else {
                header('X-Integrity-Status: OK');
            }
        }

        // ISO 27001 A.12.4.1 — Audit file download
        try {
            \App\Services\AuditService::log('file_downloaded', 'file', (int) $id, null, [
                'original_name' => $file['original_name'],
                'size_bytes'    => $file['size_bytes'] ?? null,
            ], $userId);
        } catch (\Throwable) {
            // Never block download for audit failure
        }

        header('Content-Type: ' . $file['mime_type']);
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file['original_name']));
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }

    // ── edit (GET) ─────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $file   = $this->service->findActiveWithOwner((int) $id);
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        if (!$file) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }
        if (!$admin && (int) $file['created_by'] !== $userId) {
            http_response_code(403);
            $this->render('errors/403', []);
            return;
        }

        $folders = $this->service->listFolders($userId, $admin);
        $errors  = $_SESSION['_errors'] ?? [];
        $old     = $_SESSION['_old']    ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Files/Views/edit', [
            'fileRecord'  => $file,
            'folders'     => $folders,
            'errors'      => $errors,
            'old'         => $old,
            'pageTitle'   => t('files.breadcrumb.edit') . ' — ' . e($file['original_name']),
            'breadcrumbs' => [
                ['label' => t('files.title'), 'route' => 'files.index'],
                ['label' => e($file['original_name']), 'route' => 'files.show', 'params' => ['id' => $file['id']]],
                ['label' => t('files.breadcrumb.edit')],
            ],
        ]);
    }

    // ── update (PUT) ───────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $file   = $this->service->findActiveWithOwner((int) $id);
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        if (!$file) {
            http_response_code(404);
            exit;
        }
        if (!$admin && (int) $file['created_by'] !== $userId) {
            http_response_code(403);
            exit;
        }

        $validator = new Validator();
        $validator->validate($_POST, [
            'folder'      => 'nullable|max:200',
            'description' => 'nullable|max:500',
            'tags'        => 'nullable|max:500',
        ], [
            'folder'      => t('files.field.folder'),
            'description' => t('files.field.description'),
            'tags'        => t('files.field.tags'),
        ]);
        $errors = $validator->errors();
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            header('Location: ' . route('files.edit', ['id' => $id]));
            exit;
        }

        $meta = $this->cleanPost(['description', 'tags', 'folder']);
        $allowedVisibility = ['private', 'internal'];
        $rawVisibility = (string) ($_POST['visibility'] ?? 'private');
        $meta['visibility'] = in_array($rawVisibility, $allowedVisibility, true) ? $rawVisibility : 'private';
        $this->service->update((int) $id, $meta);
        flash_success(t('files.flash.meta_updated'));
        header('Location: ' . route('files.show', ['id' => $id]));
        exit;
    }

    // ── destroy (DELETE) ───────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $file   = $this->service->find((int) $id);
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        if (!$file) {
            http_response_code(404);
            exit;
        }
        if (!$admin && (int) $file['created_by'] !== $userId) {
            http_response_code(403);
            exit;
        }

        $this->service->softDelete((int) $id);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.deleted'), 'warning');
            header('HX-Redirect: ' . route('files.index'));
            return;
        }

        flash_success(t('files.flash.deleted'));
        header('Location: ' . route('files.index'));
        exit;
    }

    // ── downloadZip (GET /files/download-zip?ids=1,2,3) ───────────────────

    public function downloadZip(): void
    {
        if (!class_exists('ZipArchive')) {
            http_response_code(501);
            exit(t('files.flash.zip_unavailable'));
        }

        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        // Accept ids as comma-separated string
        $cleanIds = $this->cleanGet(['ids']);
        $rawIds = array_filter(array_map('intval', explode(',', $cleanIds['ids'] ?? '')));
        if (empty($rawIds) || count($rawIds) > 100) {
            http_response_code(400);
            exit(t('files.flash.zip_select_range'));
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $tmpFile  = tempnam(sys_get_temp_dir(), 'pmt_zip_');

        $zip   = new \ZipArchive();
        $zipOk = $zip->open($tmpFile, \ZipArchive::OVERWRITE);
        if ($zipOk !== true) {
            http_response_code(500);
            exit(t('files.flash.zip_create_failed'));
        }

        $added     = 0;
        $usedNames = [];
        foreach ($rawIds as $id) {
            $file = $this->service->find($id);
            if (!$file) {
                continue;
            }
            if (!$this->canAccess($file, $userId, $admin)) {
                continue;
            }

            $filePath = $basePath . '/public/uploads/' . ($file['directory'] ?? '') . '/' . basename($file['stored_name']);
            if (!file_exists($filePath)) {
                continue;
            }

            // Ensure unique filenames in ZIP
            $name      = $file['original_name'] ?? basename($file['stored_name']);
            $base      = pathinfo($name, PATHINFO_FILENAME);
            $ext       = pathinfo($name, PATHINFO_EXTENSION);
            $finalName = $name;
            $counter   = 1;
            while (isset($usedNames[$finalName])) {
                $finalName = $base . '_' . $counter . ($ext ? '.' . $ext : '');
                $counter++;
            }
            $usedNames[$finalName] = true;

            $zip->addFile($filePath, $finalName);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            http_response_code(404);
            exit(t('files.flash.zip_no_files'));
        }

        $filename = 'file_' . date('Ymd_His') . '.zip';

        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache');

        readfile($tmpFile);
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        exit;
    }

    // ── bulkDestroy (POST /files/bulk-delete) ──────────────────────────────

    public function bulkDestroy(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        $rawIds = array_filter(array_map('intval', $_POST['ids'] ?? []));
        // Non-admin: only own files
        $ids = [];
        foreach ($rawIds as $id) {
            $file = $this->service->find($id);
            if ($file && ((int) $file['created_by'] === $userId || $admin)) {
                $ids[] = $id;
            }
        }

        $count = $this->service->bulkSoftDelete($ids);

        flash_success(t('files.flash.bulk_deleted', ['count' => $count]));
        header('Location: ' . route('files.index'));
        exit;
    }

    // ── storeFolder (POST /files/folders) ─────────────────────────────────

    public function storeFolder(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');
        $name   = trim($this->cleanPost(['folder'])['folder'] ?? '');

        try {
            $this->service->createFolder($userId, $name);
        } catch (\InvalidArgumentException $e) {
            if ($this->isHtmxRequest()) {
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
            header('Location: ' . route('files.index'));
            exit;
        }

        $folders      = $this->service->listFolders($userId, $admin);
        $folderCounts = $this->service->folderCounts($userId, $admin);
        $filters      = ['folder' => $this->cleanGet(['folder'])['folder'] ?? ''];

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.folder_created'), 'success', ['source' => 'files-folders']);
            $this->renderPartial('Files/Views/partials/folder_sidebar', [
                'folders'      => $folders,
                'folderCounts' => $folderCounts,
                'filters'      => $filters,
            ]);
            return;
        }

        flash_success(t('files.flash.folder_created'));
        header('Location: ' . route('files.index'));
        exit;
    }

    // ── renameFolder (PUT /files/folders/rename) ───────────────────────────

    public function renameFolder(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');
        $clean  = $this->cleanPost(['old_folder', 'new_folder']);
        $old    = trim($clean['old_folder'] ?? '');
        $new    = trim($clean['new_folder'] ?? '');

        try {
            $this->service->renameFolder($userId, $admin, $old, $new);
        } catch (\InvalidArgumentException $e) {
            if ($this->isHtmxRequest()) {
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
            header('Location: ' . route('files.index'));
            exit;
        }

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('files.flash.folder_renamed'));
            header('HX-Redirect: ' . route('files.index') . '?folder=' . urlencode($new));
            return;
        }

        flash_success(t('files.flash.folder_renamed'));
        header('Location: ' . route('files.index') . '?folder=' . urlencode($new));
        exit;
    }

    // ── destroyFolder (DELETE /files/folders) ──────────────────────────────

    public function destroyFolder(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');
        $folder = trim($this->cleanPost(['folder'])['folder'] ?? '');

        try {
            $count = $this->service->destroyFolder($userId, $admin, $folder);
        } catch (\InvalidArgumentException $e) {
            if ($this->isHtmxRequest()) {
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
            header('Location: ' . route('files.index'));
            exit;
        }

        $msg = $count > 0
            ? t('files.flash.folder_deleted_moved', ['count' => $count])
            : t('files.flash.folder_deleted');

        if ($this->isHtmxRequest()) {
            $this->hxToast($msg, 'warning');
            header('HX-Redirect: ' . route('files.index'));
            return;
        }

        flash_success($msg);
        header('Location: ' . route('files.index'));
        exit;
    }

    // ── preview (GET — inline serve) ───────────────────────────────────────

    public function preview(string $id): void
    {
        $file = $this->service->findActiveWithOwner((int) $id);
        if (!$file) {
            http_response_code(404);
            exit;
        }

        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        if (!$this->canAccess($file, $userId, $admin)) {
            http_response_code(403);
            exit;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $filePath = $basePath . '/public/uploads/' . $file['directory'] . '/' . basename($file['stored_name']);

        if (!file_exists($filePath)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $file['mime_type']);
        header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($file['original_name']));
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=300');

        // ISO 27001 A.12.4.1 — Audit file preview
        try {
            \App\Services\AuditService::log('file_previewed', 'file', (int) $id, null, [
                'original_name' => $file['original_name'],
                'mime_type'     => $file['mime_type'],
            ], $userId);
        } catch (\Throwable $e) {
            app_log('error', self::class . ': audit log file preview failed: ' . $e->getMessage());
        }

        readfile($filePath);
        exit;
    }

    // ── previewModal (GET — HTMX modal partial) ────────────────────────────

    public function previewModal(string $id): void
    {
        $file = $this->service->findActiveWithOwner((int) $id);
        if (!$file) {
            http_response_code(404);
            return;
        }

        $user   = auth();
        $userId = (int) $user['id'];
        $admin  = has_permission('files.admin');

        if (!$this->canAccess($file, $userId, $admin)) {
            http_response_code(403);
            return;
        }

        $previewType = $this->resolvePreviewType($file['mime_type']);
        $previewUrl  = route('files.preview', ['id' => $file['id']]);

        $this->renderPartial('Files/Views/partials/preview_modal_body', [
            'fileRecord'  => $file,
            'previewType' => $previewType,
            'previewUrl'  => $previewUrl,
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function canAccess(array $file, int $userId, bool $admin): bool
    {
        return $this->service->canUserAccess($file, $userId, $admin);
    }

    private function isOwnerOrAdmin(array $file, int $userId): bool
    {
        return has_permission('files.admin') || (int) ($file['created_by'] ?? 0) === $userId;
    }

    private function handleShare(string $id, string $targetType): void
    {
        $fileId = (int) $id;
        $user = auth();
        $userId = (int) $user['id'];
        $file = $this->service->findActiveWithOwner($fileId);

        if (!$file) {
            flash_error(t('files.flash.not_found'));
            header('Location: ' . route('files.index'));
            exit;
        }

        if (!$this->isOwnerOrAdmin($file, $userId) || !has_permission('files.share')) {
            flash_error(t('files.flash.share_denied'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $clean = $this->cleanPost(['permission']);
        $permission = (string) ($clean['permission'] ?? 'view');
        if (!in_array($permission, ['view', 'edit'], true)) {
            flash_error(t('files.flash.share_perm_invalid'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $rawTarget = $_POST['target_id'] ?? $_POST[$targetType . '_id'] ?? '';
        $targetId = (int) $rawTarget;
        if ($targetId <= 0) {
            flash_error(t('files.flash.share_target_invalid'));
            header('Location: ' . route('files.show', ['id' => $fileId]));
            exit;
        }

        $ok = $targetType === 'user'
            ? $this->service->shareWithUser($fileId, $targetId, $permission, $userId)
            : $this->service->shareWithRole($fileId, $targetId, $permission, $userId);

        flash_success($ok ? t('files.flash.share_updated') : t('files.flash.share_not_changed'));
        header('Location: ' . route('files.show', ['id' => $fileId]));
        exit;
    }

    private function resolvePreviewType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with($mimeType, 'text/')) {
            return 'text';
        }
        return 'icon';
    }
}
