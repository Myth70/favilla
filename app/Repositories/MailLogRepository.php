<?php

declare(strict_types=1);

namespace App\Repositories;

class MailLogRepository extends BaseRepository
{
    protected string $table = 'mail_log';

    public function listPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(to_email LIKE ? OR subject LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowedSorts = ['id', 'to_email', 'subject', 'status', 'created_at'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'created_at';
        $dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Count
        $countSql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Data
        $offset  = ($page - 1) * $perPage;
        $dataSql = "SELECT ml.*, u.name AS created_by_name
                    FROM {$this->table} ml
                    LEFT JOIN users u ON ml.created_by = u.id
                    {$whereClause}
                    ORDER BY ml.{$sort} {$dir}
                    LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($dataSql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $rows = $stmt->fetchAll();

        return [
            'data'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    public function countByStatus(): array
    {
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) as cnt FROM {$this->table} GROUP BY status"
        );
        $result = ['sent' => 0, 'failed' => 0, 'logged' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    public function cleanup(int $daysOld): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    }
}
