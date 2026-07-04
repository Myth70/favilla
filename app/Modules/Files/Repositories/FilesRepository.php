<?php

declare(strict_types=1);

namespace App\Modules\Files\Repositories;

use App\Repositories\BaseRepository;

class FilesRepository extends BaseRepository
{
    protected string $table = 'files';
    protected bool $softDelete = true;
    protected bool $timestamps = true;
    protected array $fillable = [
        'original_name', 'stored_name', 'directory', 'mime_type',
        'extension', 'size_bytes', 'checksum_sha256', 'folder', 'description',
        'tags', 'visibility', 'created_by',
    ];

    private const SORTABLE_COLUMNS = [
        'id', 'original_name', 'size_bytes', 'mime_type',
        'extension', 'folder', 'visibility', 'created_at',
    ];

    /**
     * Paginated list of files.
     *
     * Visibility rule:
     *  - normal user: own files + visibility='internal'
     *  - admin ($adminView=true): all files
     */
    public function listPaginated(
        array $filters,
        int   $userId,
        bool  $adminView = false,
        int   $page      = 1,
        int   $perPage   = 24
    ): array {
        $where  = [];
        $params = [];

        // Soft-delete filter
        $where[] = 'f.deleted_at IS NULL';

        // Visibility rule
        if (!$adminView) {
            $where[] = '(f.created_by = ? OR f.visibility = ?)';
            $params[] = $userId;
            $params[] = 'internal';
        }

        // Search
        if (!empty($filters['search'])) {
            $where[] = '(f.original_name LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Folder filter
        if (isset($filters['folder']) && $filters['folder'] !== '') {
            if (!empty($filters['folder_recursive'])) {
                $where[]  = 'f.folder LIKE ?';
                $params[] = $filters['folder'] . '%';
            } else {
                $where[]  = 'f.folder = ?';
                $params[] = $filters['folder'];
            }
        }

        // MIME group filter
        if (!empty($filters['mime_group'])) {
            switch ($filters['mime_group']) {
                case 'image':
                    $where[]  = 'f.mime_type LIKE ?';
                    $params[] = 'image/%';
                    break;
                case 'document':
                    $where[]  = 'f.mime_type LIKE ? AND f.mime_type NOT LIKE ? AND f.mime_type NOT LIKE ? AND f.mime_type NOT LIKE ?';
                    $params[] = 'application/%';
                    $params[] = 'application/zip';
                    $params[] = 'application/x-rar%';
                    $params[] = 'application/x-7z%';
                    break;
                case 'archive':
                    $where[]  = '(f.mime_type IN (?,?,?,?))';
                    $params[] = 'application/zip';
                    $params[] = 'application/x-rar-compressed';
                    $params[] = 'application/x-7z-compressed';
                    $params[] = 'application/gzip';
                    break;
                case 'text':
                    $where[]  = 'f.mime_type LIKE ?';
                    $params[] = 'text/%';
                    break;
            }
        }

        // Visibility filter (admin explicit filter)
        if (!empty($filters['visibility']) && in_array($filters['visibility'], ['private', 'internal'], true)) {
            $where[]  = 'f.visibility = ?';
            $params[] = $filters['visibility'];
        }

        if (!empty($filters['scope'])) {
            switch ($filters['scope']) {
                case 'mine':
                    $where[] = 'f.created_by = ?';
                    $params[] = $userId;
                    break;
                case 'shared':
                    $where[] = 'f.visibility = ?';
                    $params[] = 'internal';
                    break;
            }
        }

        // Admin user_id filter
        if ($adminView && !empty($filters['user_id'])) {
            $where[]  = 'f.created_by = ?';
            $params[] = (int) $filters['user_id'];
        }

        // Date range filters (admin)
        if (!empty($filters['date_from'])) {
            $where[]  = 'f.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'f.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sort
        $sort = in_array($filters['sort'] ?? '', self::SORTABLE_COLUMNS, true)
            ? $filters['sort'] : 'created_at';
        $dir  = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT f.*, u.name AS uploader_name,
                   (SELECT COUNT(*) FROM file_versions fv WHERE fv.file_id = f.id) AS version_count,
                   (SELECT COUNT(*) FROM file_shares fs WHERE fs.file_id = f.id) AS share_count
                FROM files f
                LEFT JOIN users u ON u.id = f.created_by
                {$whereClause}
                ORDER BY f.{$sort} {$dir}
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        // Total count
        $countSql  = "SELECT COUNT(*) FROM files f {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * Find a single file with uploader name (JOIN users).
     */
    /**
     * Find a single file with uploader name (JOIN users).
     * Includes soft-deleted records so callers can distinguish state.
     */
    public function findWithOwner(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT f.*, u.name AS uploader_name
             FROM files f
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find a single active (non-deleted) file with uploader name.
     */
    public function findActiveWithOwner(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT f.*, u.name AS uploader_name
             FROM files f
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.id = ? AND f.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Paginated list of soft-deleted files (admin trash).
     */
    public function listDeleted(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where  = ['f.deleted_at IS NOT NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(f.original_name LIKE ? OR f.description LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $sql = "SELECT f.*, u.name AS uploader_name
                FROM files f
                LEFT JOIN users u ON u.id = f.created_by
                {$whereClause}
                ORDER BY f.deleted_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM files f {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * Soft-delete a single file record.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE files SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Restore a soft-deleted file.
     */
    public function restore(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE files SET deleted_at = NULL WHERE id = ?');
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Hard-delete a single record.
     */
    public function hardDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ?');
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Bulk soft-delete by ID list. Returns affected row count.
     */
    public function bulkSoftDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $ids         = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->pdo->prepare(
            "UPDATE files SET deleted_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /**
     * Bulk hard-delete already soft-deleted records. Returns affected row count.
     */
    public function bulkHardDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $ids          = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->pdo->prepare(
            "DELETE FROM files WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL"
        );
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /**
     * Find multiple soft-deleted files by ID list (for bulk purge).
     */
    public function findDeletedByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ids          = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->pdo->prepare(
            "SELECT id, stored_name, directory, original_name FROM files WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    /**
     * Copertura checksum dei file attivi (per il probe di integrità ISO 27001).
     * Returns ['total' => N, 'checked' => N].
     */
    public function countChecksumCoverage(): array
    {
        $row = $this->pdo->query(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN checksum_sha256 IS NOT NULL THEN 1 ELSE 0 END) AS checked
             FROM files WHERE deleted_at IS NULL'
        )->fetch();

        return [
            'total'   => (int) ($row['total'] ?? 0),
            'checked' => (int) ($row['checked'] ?? 0),
        ];
    }

    /**
     * Count active files grouped by MIME category.
     * Returns ['image' => N, 'document' => N, 'archive' => N, 'text' => N, 'other' => N].
     */
    public function countByMimeGroup(): array
    {
        $sql = "SELECT
                  CASE
                    WHEN mime_type LIKE 'image/%' THEN 'image'
                    WHEN mime_type IN ('application/zip','application/x-rar-compressed',
                                       'application/x-7z-compressed','application/gzip') THEN 'archive'
                    WHEN mime_type LIKE 'text/%' THEN 'text'
                    WHEN mime_type LIKE 'application/%' THEN 'document'
                    ELSE 'other'
                  END AS mime_group,
                  COUNT(*) AS cnt
                FROM files
                WHERE deleted_at IS NULL
                GROUP BY mime_group";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();

        $result = ['image' => 0, 'document' => 0, 'archive' => 0, 'text' => 0, 'other' => 0];
        foreach ($rows as $row) {
            $result[$row['mime_group']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Total bytes of all active (non-deleted) files.
     */
    public function totalSize(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(size_bytes),0) FROM files WHERE deleted_at IS NULL');
        return (int) $stmt->fetchColumn();
    }

    /**
     * Total count of active (non-deleted) files.
     */
    public function totalCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM files WHERE deleted_at IS NULL');
        return (int) $stmt->fetchColumn();
    }

    /**
     * List all distinct folder paths in use (UNION with user_folders).
     */
    public function listFolders(int $userId, bool $adminView = false): array
    {
        if ($adminView) {
            $stmt = $this->pdo->prepare(
                "SELECT folder FROM (
                    SELECT DISTINCT folder FROM files WHERE deleted_at IS NULL AND folder != ''
                    UNION
                    SELECT folder FROM user_folders WHERE user_id = ?
                 ) AS combined WHERE folder != '' ORDER BY folder"
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT folder FROM (
                    SELECT DISTINCT folder FROM files
                    WHERE deleted_at IS NULL AND folder != ''
                      AND (created_by = ? OR visibility = 'internal')
                    UNION
                    SELECT folder FROM user_folders WHERE user_id = ?
                 ) AS combined WHERE folder != '' ORDER BY folder"
            );
            $stmt->execute([$userId, $userId]);
        }
        return array_column($stmt->fetchAll(), 'folder');
    }

    /**
     * Count active files per folder visible to the user.
     * Returns ['folder_name' => count, ...].
     */
    public function folderCounts(int $userId, bool $adminView = false): array
    {
        if ($adminView) {
            $stmt = $this->pdo->query(
                "SELECT folder, COUNT(*) AS cnt FROM files WHERE deleted_at IS NULL AND folder != '' GROUP BY folder"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT folder, COUNT(*) AS cnt FROM files
                 WHERE deleted_at IS NULL AND folder != ''
                   AND (created_by = ? OR visibility = 'internal')
                 GROUP BY folder"
            );
            $stmt->execute([$userId]);
        }
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['folder']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Insert a folder entry in user_folders (ignored if already exists).
     */
    public function createFolder(int $userId, string $folder): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO user_folders (user_id, folder) VALUES (?, ?)'
        );
        return $stmt->execute([$userId, $folder]);
    }

    /**
     * Ritorna la prossima versione incrementale per un file.
     */
    public function nextVersionNumber(int $fileId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_no), 0) + 1 FROM file_versions WHERE file_id = ?');
        $stmt->execute([$fileId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Salva uno snapshot di versione per un file.
     */
    public function createVersion(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO file_versions
                (file_id, version_no, original_name, stored_name, mime_type, extension, size_bytes, checksum_sha256, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['file_id'],
            (int) $data['version_no'],
            (string) ($data['original_name'] ?? ''),
            (string) ($data['stored_name'] ?? ''),
            (string) ($data['mime_type'] ?? ''),
            (string) ($data['extension'] ?? ''),
            (int) ($data['size_bytes'] ?? 0),
            $data['checksum_sha256'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Versioni disponibili per un file.
     */
    public function listVersions(int $fileId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, u.name AS created_by_name
             FROM file_versions v
             LEFT JOIN users u ON u.id = v.created_by
             WHERE v.file_id = ?
             ORDER BY v.version_no DESC'
        );
        $stmt->execute([$fileId]);
        return $stmt->fetchAll();
    }

    public function findVersion(int $fileId, int $versionNo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM file_versions WHERE file_id = ? AND version_no = ? LIMIT 1'
        );
        $stmt->execute([$fileId, $versionNo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function restoreVersionMetadata(int $fileId, array $version): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE files
             SET original_name = ?,
                 stored_name = ?,
                 mime_type = ?,
                 extension = ?,
                 size_bytes = ?,
                 checksum_sha256 = ?
             WHERE id = ? AND deleted_at IS NULL'
        );

        return $stmt->execute([
            (string) ($version['original_name'] ?? ''),
            (string) ($version['stored_name'] ?? ''),
            (string) ($version['mime_type'] ?? ''),
            (string) ($version['extension'] ?? ''),
            (int) ($version['size_bytes'] ?? 0),
            $version['checksum_sha256'] ?? null,
            $fileId,
        ]) && $stmt->rowCount() > 0;
    }

    public function listShares(int $fileId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fs.file_id, fs.target_type, fs.target_id, fs.permission, fs.created_at,
                    u.name AS user_name,
                    r.name AS role_name
             FROM file_shares fs
             LEFT JOIN users u ON fs.target_type = 'user' AND fs.target_id = u.id
             LEFT JOIN roles r ON fs.target_type = 'role' AND fs.target_id = r.id
             WHERE fs.file_id = ?
             ORDER BY fs.target_type ASC, COALESCE(u.name, r.name) ASC"
        );
        $stmt->execute([$fileId]);
        return $stmt->fetchAll();
    }

    /**
     * Verifica se un utente ha ACL esplicita su un file.
     */
    public function userHasShareAccess(int $fileId, int $userId, array $roleIds = []): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM file_shares
             WHERE file_id = ?
               AND (
                 (target_type = 'user' AND target_id = ?)
               )"
        );
        $stmt->execute([$fileId, $userId]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            return true;
        }

        if ($roleIds === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT COUNT(*) FROM file_shares WHERE file_id = ? AND target_type = 'role' AND target_id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$fileId], array_map('intval', $roleIds)));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea o aggiorna una condivisione ACL.
     */
    public function upsertShare(int $fileId, string $targetType, int $targetId, string $permission, ?int $createdBy = null): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO file_shares (file_id, target_type, target_id, permission, created_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE permission = VALUES(permission), created_by = VALUES(created_by)'
        );
        return $stmt->execute([$fileId, $targetType, $targetId, $permission, $createdBy]);
    }

    /**
     * Elimina una condivisione ACL.
     */
    public function removeShare(int $fileId, string $targetType, int $targetId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM file_shares WHERE file_id = ? AND target_type = ? AND target_id = ?');
        return $stmt->execute([$fileId, $targetType, $targetId]);
    }

    /**
     * Rename a folder: updates files.folder in bulk + updates user_folders entry.
     * Returns number of files updated.
     */
    public function renameFolder(int $userId, bool $adminView, string $old, string $new): int
    {
        $this->pdo->beginTransaction();
        try {
            // Update files
            if ($adminView) {
                $stmt = $this->pdo->prepare(
                    'UPDATE files SET folder = ? WHERE folder = ? AND deleted_at IS NULL'
                );
                $stmt->execute([$new, $old]);
            } else {
                $stmt = $this->pdo->prepare(
                    'UPDATE files SET folder = ? WHERE folder = ? AND created_by = ? AND deleted_at IS NULL'
                );
                $stmt->execute([$new, $old, $userId]);
            }
            $affected = $stmt->rowCount();

            // Move user_folders entry
            $del = $this->pdo->prepare(
                'DELETE FROM user_folders WHERE user_id = ? AND folder = ?'
            );
            $del->execute([$userId, $old]);

            $ins = $this->pdo->prepare(
                'INSERT IGNORE INTO user_folders (user_id, folder) VALUES (?, ?)'
            );
            $ins->execute([$userId, $new]);

            $this->pdo->commit();
            return $affected;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Soft-delete all files in a folder and remove the user_folders entry.
     * Returns number of files soft-deleted.
     */
    public function deleteFolderFiles(int $userId, bool $adminView, string $folder): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($adminView) {
                $stmt = $this->pdo->prepare(
                    'UPDATE files SET deleted_at = NOW() WHERE folder = ? AND deleted_at IS NULL'
                );
                $stmt->execute([$folder]);
            } else {
                $stmt = $this->pdo->prepare(
                    'UPDATE files SET deleted_at = NOW() WHERE folder = ? AND created_by = ? AND deleted_at IS NULL'
                );
                $stmt->execute([$folder, $userId]);
            }
            $affected = $stmt->rowCount();

            $del = $this->pdo->prepare(
                'DELETE FROM user_folders WHERE user_id = ? AND folder = ?'
            );
            $del->execute([$userId, $folder]);

            $this->pdo->commit();
            return $affected;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * List active users for admin filter dropdowns.
     */
    public function listUsers(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listRoles(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM roles ORDER BY name');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count and total size of files uploaded by a user.
     */
    public function countAndSizeByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total, COALESCE(SUM(size_bytes), 0) AS size
             FROM files
             WHERE deleted_at IS NULL AND created_by = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return ['total' => (int) $row['total'], 'size' => (int) $row['size']];
    }

    /**
     * Most recent files uploaded by a user.
     */
    public function recentByUser(int $userId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT original_name, extension, size_bytes, created_at
             FROM files
             WHERE deleted_at IS NULL AND created_by = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Lightweight search for autocomplete / live search widget.
     */
    public function search(string $query, int $userId, bool $adminView = false, int $limit = 20): array
    {
        $like   = '%' . $query . '%';
        $params = [$like, $like, $like];

        if ($adminView) {
            $visClause = '';
        } else {
            $visClause = ' AND (f.created_by = ? OR f.visibility = ?)';
            $params[]  = $userId;
            $params[]  = 'internal';
        }

        $stmt = $this->pdo->prepare(
            "SELECT f.id, f.original_name, f.extension, f.mime_type, f.size_bytes, f.folder
             FROM files f
             WHERE f.deleted_at IS NULL
               AND (f.original_name LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)
               {$visClause}
             ORDER BY f.created_at DESC
             LIMIT ?"
        );
        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
