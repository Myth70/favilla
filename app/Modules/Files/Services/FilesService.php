<?php

declare(strict_types=1);

namespace App\Modules\Files\Services;

use App\Modules\Files\Repositories\FilesRepository;
use App\Services\AuditService;
use App\Services\FileUploadService;

class FilesService
{
    /** Maximum file size for the Files module (50 MB). */
    public const MAX_BYTES = 52428800;

    /** MIME types accepted by the Files module upload. */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** Human-readable accept string for HTML input[accept]. */
    public const ACCEPT_ATTR = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.zip,.rar,.7z,.gz,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp';

    private FilesRepository $repo;

    public function __construct(FilesRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Copertura checksum dei file attivi: ['total' => N, 'checked' => N].
     * Usata dal probe di integrità di HealthCheck (niente SQL cross-modulo).
     */
    public function checksumCoverage(): array
    {
        return $this->repo->countChecksumCoverage();
    }

    /**
     * Upload a file to disk and insert the DB record.
     * Rolls back the physical file if DB insert fails.
     *
     * @param array $uploadedFile  Entry from $_FILES
     * @param array $meta          Keys: description, folder, tags, visibility
     * @param int   $userId
     * @return int  New file record ID
     * @throws \RuntimeException on validation / I/O failure
     */
    public function store(array $uploadedFile, array $meta, int $userId): int
    {
        $result = FileUploadService::uploadFile(
            $uploadedFile,
            'files',
            'file_',
            self::MAX_BYTES,
            self::ALLOWED_MIMES
        );

        try {
            // ISO 27001 A.12.2 — Compute SHA-256 checksum for file integrity
            $uploadDir = realpath(__DIR__ . '/../../../../public/uploads/files') ?: '';
            $physicalPath = $uploadDir . DIRECTORY_SEPARATOR . basename($result['filename']);
            $checksum = file_exists($physicalPath) ? hash_file('sha256', $physicalPath) : null;

            $id = $this->repo->create([
                'original_name'   => basename($uploadedFile['name'] ?? 'file'),
                'stored_name'     => $result['filename'],
                'directory'       => 'files',
                'mime_type'       => $result['mime'],
                'extension'       => $result['extension'],
                'size_bytes'      => $result['size'],
                'checksum_sha256' => $checksum,
                'folder'          => $this->sanitizeFolder($meta['folder'] ?? ''),
                'description'     => !empty($meta['description']) ? substr(trim($meta['description']), 0, 500) : null,
                'tags'            => !empty($meta['tags']) ? $this->normalizeTags($meta['tags']) : null,
                'visibility'      => in_array($meta['visibility'] ?? '', ['private', 'internal'], true)
                                     ? $meta['visibility'] : 'private',
                'created_by'      => $userId,
            ]);
        } catch (\Throwable $e) {
            // Physical file already on disk — clean up to avoid orphan
            FileUploadService::delete($result['filename'], 'files');
            throw $e;
        }

        AuditService::log('file_uploaded', 'file', $id, null, [
            'original_name' => basename($uploadedFile['name'] ?? ''),
            'size_bytes'    => $result['size'],
            'mime_type'     => $result['mime'],
        ]);

        return $id;
    }

    /**
     * Update editable metadata (does not touch the physical file).
     */
    public function update(int $fileId, array $meta): bool
    {
        return $this->repo->update($fileId, [
            'description' => !empty($meta['description']) ? substr(trim($meta['description']), 0, 500) : null,
            'folder'      => $this->sanitizeFolder($meta['folder'] ?? ''),
            'tags'        => !empty($meta['tags']) ? $this->normalizeTags($meta['tags']) : null,
            'visibility'  => in_array($meta['visibility'] ?? '', ['private', 'internal'], true)
                             ? $meta['visibility'] : 'private',
        ]);
    }

    /**
     * Soft-delete a file record (physical file stays on disk).
     */
    public function softDelete(int $fileId): bool
    {
        $ok = $this->repo->softDelete($fileId);
        if ($ok) {
            AuditService::log('file_deleted', 'file', $fileId, null, null);
        }
        return $ok;
    }

    /**
     * Hard-delete: remove DB record AND physical file.
     */
    public function purge(int $fileId): bool
    {
        // Use findWithTrashed so we can locate soft-deleted records (deleted_at IS NOT NULL)
        $file = $this->repo->findWithTrashed($fileId);
        if (!$file) {
            return false;
        }
        FileUploadService::delete($file['stored_name'], $file['directory']);
        $ok = $this->repo->hardDelete($fileId);
        if ($ok) {
            AuditService::log('file_purged', 'file', $fileId, ['name' => $file['original_name']], null);
        }
        return $ok;
    }

    /**
     * Restore a soft-deleted file record.
     */
    public function restore(int $fileId): bool
    {
        $ok = $this->repo->restore($fileId);
        if ($ok) {
            AuditService::log('file_restored', 'file', $fileId, null, null);
        }
        return $ok;
    }

    /**
     * Bulk soft-delete by ID list. Logs each deletion for audit trail.
     */
    public function bulkSoftDelete(array $ids): int
    {
        $count = $this->repo->bulkSoftDelete($ids);
        if ($count > 0) {
            AuditService::log('files_bulk_deleted', 'file', null, null, [
                'ids'   => $ids,
                'count' => $count,
            ]);
        }
        return $count;
    }

    /**
     * Bulk hard-purge (only already soft-deleted records).
     * Handles physical file removal for each record. Logs audit.
     */
    public function bulkPurge(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $ids = array_map('intval', $ids);

        // Single query to fetch all soft-deleted files
        $files = $this->repo->findDeletedByIds($ids);
        if (empty($files)) {
            return 0;
        }

        // Delete physical files from disk
        foreach ($files as $file) {
            FileUploadService::delete($file['stored_name'], $file['directory']);
        }

        // Bulk delete DB records
        $deletedIds = array_column($files, 'id');
        $count = $this->repo->bulkHardDelete($deletedIds);

        if ($count > 0) {
            $names = array_column($files, 'original_name');
            AuditService::log('files_bulk_purged', 'file', null, ['names' => $names], null);
        }

        return $count;
    }

    /**
     * Paginated list of files (delegates to repository).
     */
    public function listPaginated(
        array $filters,
        int   $userId,
        bool  $admin,
        int   $page,
        int   $perPage = 24
    ): array {
        return $this->repo->listPaginated($filters, $userId, $admin, $page, $perPage);
    }

    /**
     * List distinct folder paths visible to the user.
     */
    public function listFolders(int $userId, bool $admin): array
    {
        return $this->repo->listFolders($userId, $admin);
    }

    /**
     * Count active files per folder visible to the user.
     */
    public function folderCounts(int $userId, bool $admin): array
    {
        return $this->repo->folderCounts($userId, $admin);
    }

    /**
     * Find a single file record with uploader name (includes soft-deleted).
     */
    public function findWithOwner(int $id): ?array
    {
        return $this->repo->findWithOwner($id);
    }

    /**
     * Find a single active (non-deleted) file record with uploader name.
     */
    public function findActiveWithOwner(int $id): ?array
    {
        return $this->repo->findActiveWithOwner($id);
    }

    /**
     * Find a single file record by ID.
     */
    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Verifica accesso file con ACL estese (owner/admin/internal/share esplicito).
     */
    public function canUserAccess(array $file, int $userId, bool $admin): bool
    {
        if ($admin) {
            return true;
        }
        if ((int) ($file['created_by'] ?? 0) === $userId) {
            return true;
        }
        if (($file['visibility'] ?? '') === 'internal') {
            return true;
        }

        $roleIds = $this->userRoleIds($userId);
        return $this->repo->userHasShareAccess((int) $file['id'], $userId, $roleIds);
    }

    /**
     * Registra una nuova versione snapshot di un file.
     */
    public function snapshotVersion(int $fileId, array $fileRecord, ?int $createdBy = null): int
    {
        $nextVersion = $this->repo->nextVersionNumber($fileId);
        return $this->repo->createVersion([
            'file_id' => $fileId,
            'version_no' => $nextVersion,
            'original_name' => $fileRecord['original_name'] ?? '',
            'stored_name' => $fileRecord['stored_name'] ?? '',
            'mime_type' => $fileRecord['mime_type'] ?? '',
            'extension' => $fileRecord['extension'] ?? '',
            'size_bytes' => (int) ($fileRecord['size_bytes'] ?? 0),
            'checksum_sha256' => $fileRecord['checksum_sha256'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    public function listVersions(int $fileId): array
    {
        return $this->repo->listVersions($fileId);
    }

    public function listShares(int $fileId): array
    {
        return $this->repo->listShares($fileId);
    }

    public function restoreVersion(int $fileId, int $versionNo, ?int $actorUserId = null): bool
    {
        $version = $this->repo->findVersion($fileId, $versionNo);
        if (!$version) {
            throw new \RuntimeException('Versione non trovata.');
        }

        $restored = $this->repo->restoreVersionMetadata($fileId, $this->buildRestorePayload($version));
        if ($restored) {
            AuditService::log('file_version_restored', 'file', $fileId, null, [
                'version_no' => $versionNo,
                'original_name' => $version['original_name'] ?? null,
            ], $actorUserId);
        }

        return $restored;
    }

    public function buildRestorePayload(array $version): array
    {
        return [
            'original_name' => (string) ($version['original_name'] ?? ''),
            'stored_name' => (string) ($version['stored_name'] ?? ''),
            'mime_type' => (string) ($version['mime_type'] ?? ''),
            'extension' => (string) ($version['extension'] ?? ''),
            'size_bytes' => (int) ($version['size_bytes'] ?? 0),
            'checksum_sha256' => $version['checksum_sha256'] ?? null,
        ];
    }

    public function shareWithUser(int $fileId, int $targetUserId, string $permission, ?int $createdBy = null): bool
    {
        $perm = $permission === 'edit' ? 'edit' : 'view';
        return $this->repo->upsertShare($fileId, 'user', $targetUserId, $perm, $createdBy);
    }

    public function shareWithRole(int $fileId, int $roleId, string $permission, ?int $createdBy = null): bool
    {
        $perm = $permission === 'edit' ? 'edit' : 'view';
        return $this->repo->upsertShare($fileId, 'role', $roleId, $perm, $createdBy);
    }

    public function revokeShare(int $fileId, string $targetType, int $targetId): bool
    {
        if (!in_array($targetType, ['user', 'role'], true)) {
            return false;
        }
        return $this->repo->removeShare($fileId, $targetType, $targetId);
    }

    /**
     * Paginated list of soft-deleted files (admin trash).
     */
    public function listDeleted(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->repo->listDeleted($filters, $page, $perPage);
    }

    /**
     * All filtered files without pagination (for CSV export).
     * Uses a reasonable upper limit to prevent memory exhaustion.
     */
    public function listAllFiltered(array $filters, int $limit = 10000): array
    {
        return $this->repo->listPaginated($filters, 0, true, 1, $limit);
    }

    /**
     * List active users for admin filter dropdowns.
     */
    public function listUsers(): array
    {
        return $this->repo->listUsers();
    }

    public function listRoles(): array
    {
        return $this->repo->listRoles();
    }

    /**
     * Stats for a specific user (own + internal files).
     */
    public function getUserStats(int $userId): array
    {
        $data = $this->repo->countAndSizeByUser($userId);
        return [
            'total_files'   => $data['total'],
            'total_size'    => $data['size'],
            'total_size_hr' => self::humanSize($data['size']),
        ];
    }

    /**
     * Most recent files uploaded by a user.
     */
    public function getRecentByUser(int $userId, int $limit = 5): array
    {
        return $this->repo->recentByUser($userId, $limit);
    }

    /**
     * Stats for admin dashboard.
     */
    public function adminStats(): array
    {
        $byGroup = $this->repo->countByMimeGroup();
        $total   = $this->repo->totalCount();
        $size    = $this->repo->totalSize();

        return [
            'total_files'   => $total,
            'total_size'    => $size,
            'total_size_hr' => $this->humanSize($size),
            'by_group'      => $byGroup,
        ];
    }

    /**
     * Create an empty folder in user_folders.
     *
     * @throws \InvalidArgumentException if name is empty or invalid
     */
    public function createFolder(int $userId, string $name): void
    {
        $name = $this->sanitizeFolder($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Il nome della cartella non può essere vuoto.');
        }
        $this->repo->createFolder($userId, $name);
    }

    /**
     * Rename a folder (bulk-updates files.folder + user_folders entry).
     * Returns number of files updated.
     *
     * @throws \InvalidArgumentException
     */
    public function renameFolder(int $userId, bool $adminView, string $old, string $new): int
    {
        $old = $this->sanitizeFolder($old);
        $new = $this->sanitizeFolder($new);
        if ($old === '' || $new === '') {
            throw new \InvalidArgumentException('Nome cartella non valido.');
        }
        return $this->repo->renameFolder($userId, $adminView, $old, $new);
    }

    /**
     * Delete a folder: soft-deletes all files inside and removes the user_folders entry.
     * Returns number of files soft-deleted.
     *
     * @throws \InvalidArgumentException
     */
    public function destroyFolder(int $userId, bool $adminView, string $folder): int
    {
        $folder = $this->sanitizeFolder($folder);
        if ($folder === '') {
            throw new \InvalidArgumentException('Cartella non valida.');
        }
        return $this->repo->deleteFolderFiles($userId, $adminView, $folder);
    }

    /**
     * Normalise a comma-separated tag string.
     * Trims each tag, removes empties and duplicates, truncates to 500 chars.
     */
    public function normalizeTags(string $tags): ?string
    {
        $parts = array_map('trim', explode(',', $tags));
        $parts = array_filter($parts, fn (string $t) => $t !== '');
        $parts = array_unique($parts);
        if (empty($parts)) {
            return null;
        }
        return substr(implode(', ', $parts), 0, 500);
    }

    /**
     * Normalise a virtual folder path.
     * Strips leading/trailing slashes for storage consistency.
     */
    public function sanitizeFolder(string $folder): string
    {
        // Remove null bytes and trim
        $folder = str_replace("\0", '', trim($folder));
        // Strip directory traversal sequences
        $folder = preg_replace('#\.\.+#', '', $folder);
        // Normalise slashes
        $folder = preg_replace('#[/\\\\]+#', '/', $folder);
        $folder = trim($folder, '/');
        // Limit length
        return substr($folder, 0, 200);
    }

    /**
     * Font Awesome icon class for a file based on extension and MIME type.
     */
    public static function iconClass(string $ext, string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'fa-file-image fm-icon-image';
        }
        return match (true) {
            $ext === 'pdf'                            => 'fa-file-pdf fm-icon-pdf',
            in_array($ext, ['doc', 'docx', 'odt'])    => 'fa-file-word fm-icon-word',
            in_array($ext, ['xls', 'xlsx', 'ods'])    => 'fa-file-excel fm-icon-excel',
            in_array($ext, ['ppt', 'pptx'])            => 'fa-file-powerpoint fm-icon-ppt',
            in_array($ext, ['zip', 'rar', '7z', 'gz']) => 'fa-file-zipper fm-icon-zip',
            in_array($ext, ['txt', 'csv'])             => 'fa-file-lines fm-icon-text',
            default                                    => 'fa-file fm-icon-other',
        };
    }

    /**
     * Human-readable file size.
     */
    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    // ------------------------------------------------------------------
    // ISO 27001 A.12.2 — File integrity verification
    // ------------------------------------------------------------------

    /**
     * Verify file integrity by comparing stored SHA-256 checksum with current file.
     *
     * @return array{valid: bool, stored: ?string, computed: ?string, error: ?string}
     */
    public function verifyIntegrity(int $fileId): array
    {
        $file = $this->repo->find($fileId);
        if (!$file) {
            return ['valid' => false, 'stored' => null, 'computed' => null, 'error' => 'File non trovato.'];
        }

        $storedChecksum = $file['checksum_sha256'] ?? null;
        if (!$storedChecksum) {
            return ['valid' => false, 'stored' => null, 'computed' => null, 'error' => 'Checksum non disponibile per questo file.'];
        }

        $uploadDir = realpath(__DIR__ . '/../../../../public/uploads/' . $file['directory']) ?: '';
        $physicalPath = $uploadDir . DIRECTORY_SEPARATOR . basename($file['stored_name']);

        if (!file_exists($physicalPath)) {
            return ['valid' => false, 'stored' => $storedChecksum, 'computed' => null, 'error' => 'File fisico non trovato su disco.'];
        }

        $currentChecksum = hash_file('sha256', $physicalPath);

        return [
            'valid'    => hash_equals($storedChecksum, $currentChecksum),
            'stored'   => $storedChecksum,
            'computed' => $currentChecksum,
            'error'    => null,
        ];
    }

    /**
     * @return int[]
     */
    private function userRoleIds(int $userId): array
    {
        $pdo = app(\PDO::class);
        $stmt = $pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'role_id'));
    }
}
