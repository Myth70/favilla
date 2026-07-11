<?php

declare(strict_types=1);

namespace App\Modules\Backup\Controllers;

use App\Core\Controller;
use App\Modules\Backup\Services\BackupEncryptionService;
use App\Modules\Backup\Services\BackupFilesService;
use App\Modules\Backup\Services\BackupService;
use App\Services\AuditService;
use App\Services\UserService;
use App\Traits\ControllerHelpers;

class BackupController extends Controller
{
    use ControllerHelpers;

    private BackupService $service;
    private UserService $userService;

    public function __construct()
    {
        $this->service = app(BackupService::class);
        $this->userService = app(UserService::class);
    }

    /**
     * Lista backup su filesystem + storico DB.
     */
    public function index(): void
    {
        $backups      = $this->service->listBackups();
        $history      = $this->service->listHistory(50);
        $excluded     = $this->service->getExcludedTables();
        $running      = $this->service->isBackupRunning();
        $filesService = app(BackupFilesService::class);

        $this->render('Backup/Views/index', [
            'pageTitle'      => t('backup.title'),
            'backups'        => $backups,
            'history'        => $history,
            'maxCount'       => (int) env('BACKUP_MAX_COUNT', 10),
            'excludedTables' => $excluded,
            'isRunning'      => $running,
            'filesEnabled'   => $filesService->isEnabled(),
            'fileRoots'      => array_column($filesService->roots(), 'base'),
        ]);
    }

    /**
     * Crea un nuovo backup (POST).
     */
    public function store(): void
    {
        $user = auth();

        try {
            $result = $this->service->createBackup($user ? (int) $user['id'] : null);

            // Registra nel DB (formato + dettaglio per-database e per-file)
            $format = $this->service->isZipBackup($result['filename']) ? 'zip' : 'sqlgz';
            $this->service->recordHistory(
                $result['filename'],
                $result['size'],
                $result['table_count'],
                $user ? (int) $user['id'] : null,
                $format,
                $result['databases'] ?? null,
                $result['files'] ?? null
            );

            AuditService::log('backup_created', 'backup', null, null, [
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'partial'  => $result['partial'] ?? false,
            ]);

            $msg = t('backup.flash.created', ['filename' => $result['filename']]);
            if (($result['file_count'] ?? 0) > 0) {
                $msg .= ' ' . t('backup.flash.files_included', [
                    'count' => $result['file_count'],
                    'size'  => number_format(($result['files_size'] ?? 0) / 1048576, 1, ',', '.'),
                ]);
            }
            if (($result['excluded_count'] ?? 0) > 0) {
                $msg .= ' ' . t('backup.flash.excluded_count', ['count' => $result['excluded_count']]);
            }
            if (!empty($result['partial'])) {
                $msg .= t('backup.flash.partial_warning');
            }
            flash_success($msg);
            $this->notifyCurrentUser(
                t('backup.notif.completed_title'),
                $msg,
                'success',
                route('backup.index'),
                'fa-solid fa-database'
            );
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        } catch (\Throwable $e) {
            flash_error(t('backup.flash.error', ['error' => $e->getMessage()]));
        }

        header('Location: ' . route('backup.index'));
        exit;
    }

    /**
     * Download di un file backup (GET).
     */
    public function download(): void
    {
        $filename = trim($_GET['file'] ?? '');

        // Validazione CRITICA: prevenzione path traversal
        if (!$this->service->validateFilename($filename)) {
            http_response_code(400);
            exit(e(t('backup.flash.invalid_filename')));
        }

        $path = $this->service->getBackupPath($filename);
        if ($path === null) {
            http_response_code(404);
            exit(e(t('backup.flash.file_not_found')));
        }

        AuditService::log('backup_downloaded', 'backup', null, null, ['filename' => $filename]);

        $contentType = $this->service->isZipBackup($filename) ? 'application/zip' : 'application/gzip';

        // Archivio in chiaro: streaming diretto dal disco, senza caricarlo in
        // memoria (con i file inclusi un set può pesare svariati GB).
        if (!app(BackupEncryptionService::class)->isEncryptedFile($path)) {
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($path));
            header('Cache-Control: no-cache, no-store, must-revalidate');

            readfile($path);
            exit;
        }

        // Archivio cifrato: la decifratura è in-memory (dimensione già limitata
        // dal cap di cifratura).
        try {
            $contents = $this->service->readBackupContents($path);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            exit(e(t('backup.flash.read_error', ['error' => $e->getMessage()])));
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($contents));
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo $contents;
        exit;
    }

    /**
     * Elimina un file backup (POST).
     */
    public function destroy(): void
    {
        $filename = trim($_POST['file'] ?? '');

        if (!$this->service->validateFilename($filename)) {
            flash_error(t('backup.flash.invalid_filename'));
            header('Location: ' . route('backup.index'));
            exit;
        }

        $deleted = $this->service->deleteBackup($filename);
        if ($deleted) {
            // Rimuovi anche il record DB se esiste
            $this->service->deleteHistoryByFilename($filename);
            AuditService::log('backup_deleted', 'backup', null, ['filename' => $filename], null);
            flash_success(t('backup.flash.deleted'));
        } else {
            flash_error(t('backup.flash.delete_failed'));
        }

        header('Location: ' . route('backup.index'));
        exit;
    }

    /**
     * Ripristina un backup esistente (POST).
     */
    public function restore(): void
    {
        $filename = trim((string) ($_POST['file'] ?? ''));
        $confirmText = trim((string) ($_POST['confirm_text'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');

        if (!$this->service->validateFilename($filename)) {
            flash_error(t('backup.flash.invalid_filename'));
            header('Location: ' . route('backup.index'));
            exit;
        }

        $restoreKeyword = t('backup.restore_keyword');
        if (mb_strtoupper($confirmText) !== mb_strtoupper($restoreKeyword)) {
            flash_error(t('backup.flash.confirm_invalid', ['keyword' => $restoreKeyword]));
            header('Location: ' . route('backup.index'));
            exit;
        }

        $user = auth();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0 || !$this->userService->verifyPassword($userId, $currentPassword)) {
            flash_error(t('backup.flash.password_invalid'));
            header('Location: ' . route('backup.index'));
            exit;
        }

        try {
            $result = $this->service->restoreBackup($filename, $userId, true);

            AuditService::log('backup_restored', 'backup', null, null, [
                'filename' => $filename,
                'pre_restore_backup' => $result['pre_restore_backup'] ?? null,
                'statements' => $result['statements_executed'] ?? 0,
                'files_restored' => $result['files_restored'] ?? 0,
            ]);

            $msg = t('backup.flash.restored', ['filename' => $filename]);
            if (($result['files_restored'] ?? 0) > 0) {
                $msg .= ' ' . t('backup.flash.restored_files', ['count' => $result['files_restored']]);
            }
            flash_success($msg);
            $this->notifyCurrentUser(
                t('backup.notif.restored_title'),
                t('backup.notif.restored_body', ['filename' => $filename]),
                'warning',
                route('backup.index'),
                'fa-solid fa-rotate-left'
            );
        } catch (\Throwable $e) {
            $_SESSION['_flash_error'] = [
                'title' => t('backup.notif.restore_failed_title'),
                'message' => t('backup.flash.restore_failed', ['error' => $e->getMessage()]),
                'type' => 'danger',
                'channel' => 'banner',
                'persistent' => true,
                'source' => 'backup-restore',
            ];
            $this->notifyCurrentUser(
                t('backup.notif.restore_failed_title'),
                t('backup.notif.restore_failed_body', ['filename' => $filename, 'error' => $e->getMessage()]),
                'danger',
                route('backup.index'),
                'fa-solid fa-circle-xmark'
            );
        }

        header('Location: ' . route('backup.index'));
        exit;
    }
}
