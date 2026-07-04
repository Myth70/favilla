<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class HistoryRepository extends BaseRepository
{
    protected string $table = 'report_history';
    protected bool $auditable = true;
    protected string $auditEntity = 'report_history';

    protected array $fillable = [
        'template_id', 'template_name', 'module', 'source_key',
        'output_format', 'stored_filename', 'file_size', 'row_count',
        'filters_used', 'generated_by', 'generated_at', 'expires_at',
    ];

    private const SORTABLE_COLUMNS = [
        'id', 'template_name', 'module', 'output_format',
        'file_size', 'row_count', 'generated_at',
    ];

    /**
     * Paginated list of report history entries.
     *
     * Filters: q, module, format, date_from, date_to
     * Non-admin users see only their own entries.
     */
    public function listPaginated(array $filters, int $page, int $perPage, ?int $userId, bool $adminView): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $where = [];
        $params = [];

        // Non-admin sees only own entries
        if (!$adminView && $userId !== null) {
            $where[] = 'h.generated_by = ?';
            $params[] = $userId;
        }

        // Search
        if (!empty($filters['q'])) {
            $where[] = '(h.template_name LIKE ? OR h.module LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Module filter
        if (!empty($filters['module'])) {
            $where[] = 'h.module = ?';
            $params[] = $filters['module'];
        }

        // Format filter
        if (!empty($filters['format'])) {
            $where[] = 'h.output_format = ?';
            $params[] = $filters['format'];
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $where[] = 'h.generated_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'h.generated_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sort
        $sort = in_array($filters['sort'] ?? '', self::SORTABLE_COLUMNS, true)
            ? $filters['sort'] : 'generated_at';
        $dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;

        // Fetch page
        $sql = "SELECT h.*, u.name AS generator_name
                FROM report_history h
                LEFT JOIN users u ON u.id = h.generated_by
                {$whereClause}
                ORDER BY h.{$sort} {$dir}
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total count
        $countSql = "SELECT COUNT(*) FROM report_history h {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Find a history entry for a specific user (or any if admin).
     */
    public function findForUser(int $id, int $userId, bool $adminView): ?array
    {
        if ($adminView) {
            $sql = 'SELECT h.*, u.name AS generator_name
                    FROM report_history h
                    LEFT JOIN users u ON u.id = h.generated_by
                    WHERE h.id = ?
                    LIMIT 1';
            $params = [$id];
        } else {
            $sql = 'SELECT h.*, u.name AS generator_name
                    FROM report_history h
                    LEFT JOIN users u ON u.id = h.generated_by
                    WHERE h.id = ? AND h.generated_by = ?
                    LIMIT 1';
            $params = [$id, $userId];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Delete expired history entries and return the deleted rows
     * (with stored_filename for file cleanup).
     */
    public function deleteExpired(): array
    {
        // First fetch the expired rows
        $stmt = $this->pdo->prepare(
            'SELECT id, stored_filename, file_size
             FROM report_history
             WHERE expires_at IS NOT NULL AND expires_at < NOW()'
        );
        $stmt->execute();
        $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($expired)) {
            return [];
        }

        // Delete them
        $ids = array_column($expired, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $delStmt = $this->pdo->prepare(
            "DELETE FROM report_history WHERE id IN ({$placeholders})"
        );
        $delStmt->execute($ids);

        return $expired;
    }

    /**
     * Get distinct module names from history.
     */
    public function getDistinctModules(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT module FROM report_history ORDER BY module ASC'
        );

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'module');
    }

    /**
     * Get aggregate statistics for the history dashboard.
     */
    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total_reports,
                    COALESCE(SUM(file_size), 0) AS total_size,
                    COALESCE(SUM(row_count), 0) AS total_rows,
                    COUNT(DISTINCT generated_by) AS unique_users,
                    COUNT(DISTINCT module) AS unique_modules,
                    SUM(CASE WHEN output_format = 'pdf' THEN 1 ELSE 0 END) AS pdf_count,
                    SUM(CASE WHEN output_format = 'excel' THEN 1 ELSE 0 END) AS excel_count,
                    SUM(CASE WHEN output_format = 'csv' THEN 1 ELSE 0 END) AS csv_count,
                    MAX(generated_at) AS last_generated_at
                FROM report_history";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_reports'    => (int) $row['total_reports'],
            'total_size'       => (int) $row['total_size'],
            'total_rows'       => (int) $row['total_rows'],
            'unique_users'     => (int) $row['unique_users'],
            'unique_modules'   => (int) $row['unique_modules'],
            'pdf_count'        => (int) $row['pdf_count'],
            'excel_count'      => (int) $row['excel_count'],
            'csv_count'        => (int) $row['csv_count'],
            'last_generated_at' => $row['last_generated_at'],
        ];
    }
}
