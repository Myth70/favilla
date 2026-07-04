<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class AdminLogsRepository extends BaseRepository
{
    protected string $table = 'audit_logs';

    public const EXPORT_LIMIT = 10000;

    private const SORT_WHITELIST_AUDIT    = ['created_at', 'action', 'entity', 'ip'];
    private const SORT_WHITELIST_ATTEMPTS = ['created_at', 'email', 'ip_address', 'success'];
    private const SORT_WHITELIST_SESSIONS = ['last_activity', 'expires_at'];

    // ---------------------------------------------------------------
    // AUDIT LOGS
    // ---------------------------------------------------------------

    public function listAudit(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[]  = 'al.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['entity'])) {
            $where[]  = 'al.entity = ?';
            $params[] = $filters['entity'];
        }
        if (!empty($filters['ip'])) {
            $where[]  = 'al.ip LIKE ?';
            $params[] = '%' . $filters['ip'] . '%';
        }
        if (!empty($filters['search'])) {
            $q        = '%' . $filters['search'] . '%';
            $where[]  = '(al.old_value LIKE ? OR al.new_value LIKE ?)';
            $params[] = $q;
            $params[] = $q;
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'al.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'al.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sort    = in_array($filters['sort'] ?? '', self::SORT_WHITELIST_AUDIT, true)
                   ? $filters['sort'] : 'created_at';
        $dir     = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $where   = implode(' AND ', $where);
        $offset  = ($page - 1) * $perPage;

        $sql = "
            SELECT al.id, al.action, al.entity, al.entity_id,
                   al.old_value, al.new_value, al.ip, al.created_at,
                   u.name AS user_name, u.username AS user_username
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE {$where}
            ORDER BY al.{$sort} {$dir}
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) FROM audit_logs al WHERE {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public function getAuditStats(): array
    {
        $total = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM audit_logs'
        )->fetchColumn();

        $today = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()'
        )->fetchColumn();

        $actions = $this->pdo->query(
            'SELECT action, COUNT(*) AS cnt FROM audit_logs GROUP BY action ORDER BY cnt DESC LIMIT 8'
        )->fetchAll();

        return compact('total', 'today', 'actions');
    }

    public function getDistinctAuditActions(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT action FROM audit_logs ORDER BY action'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctAuditEntities(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT entity FROM audit_logs WHERE entity IS NOT NULL ORDER BY entity'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function purgeAudit(int $days): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // LOGIN ATTEMPTS
    // ---------------------------------------------------------------

    public function listAttempts(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['email'])) {
            $where[]  = 'email LIKE ?';
            $params[] = '%' . $filters['email'] . '%';
        }
        if (!empty($filters['ip'])) {
            $where[]  = 'ip_address LIKE ?';
            $params[] = '%' . $filters['ip'] . '%';
        }
        if (isset($filters['success']) && $filters['success'] !== '' && $filters['success'] !== null) {
            $where[]  = 'success = ?';
            $params[] = (int) $filters['success'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sort   = in_array($filters['sort'] ?? '', self::SORT_WHITELIST_ATTEMPTS, true)
                  ? $filters['sort'] : 'created_at';
        $dir    = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $where  = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT id, email, ip_address, success, created_at
            FROM login_attempts
            WHERE {$where}
            ORDER BY {$sort} {$dir}
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM login_attempts WHERE {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public function getAttemptsStats(): array
    {
        $total = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM login_attempts'
        )->fetchColumn();

        $todayFailed = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND DATE(created_at) = CURDATE()'
        )->fetchColumn();

        $todaySuccess = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM login_attempts WHERE success = 1 AND DATE(created_at) = CURDATE()'
        )->fetchColumn();

        return compact('total', 'todayFailed', 'todaySuccess');
    }

    public function purgeAttempts(int $days): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // SESSIONS
    // ---------------------------------------------------------------

    public function listSessions(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = 's.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['active_only'])) {
            $where[]  = 's.expires_at > NOW()';
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 's.last_activity >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 's.last_activity <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sort   = in_array($filters['sort'] ?? '', self::SORT_WHITELIST_SESSIONS, true)
                  ? $filters['sort'] : 'last_activity';
        $dir    = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $where  = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT s.id, s.user_id, s.ip, s.user_agent, s.last_activity, s.expires_at,
                   u.name AS user_name, u.username AS user_username
            FROM sessions s
            LEFT JOIN users u ON u.id = s.user_id
            WHERE {$where}
            ORDER BY s.{$sort} {$dir}
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM sessions s WHERE {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public function getSessionsStats(): array
    {
        $active = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM sessions WHERE expires_at > NOW()'
        )->fetchColumn();

        $expired = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM sessions WHERE expires_at <= NOW()'
        )->fetchColumn();

        return compact('active', 'expired');
    }

    public function purgeExpiredSessions(): int
    {
        $stmt = $this->pdo->query('DELETE FROM sessions WHERE expires_at <= NOW()');
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // USERS (helper per filtri select)
    // ---------------------------------------------------------------

    public function getUsersForFilter(): array
    {
        return $this->pdo->query(
            'SELECT id, name, username FROM users WHERE deleted_at IS NULL ORDER BY name'
        )->fetchAll();
    }

    // ---------------------------------------------------------------
    // PASSWORD RESETS
    // ---------------------------------------------------------------

    public function purgePasswordResets(int $days): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------
    // EXPORT (no pagination, max 10 000 rows per sicurezza)
    // ---------------------------------------------------------------

    public function exportAudit(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['entity'])) {
            $where[] = 'al.entity = ?';
            $params[] = $filters['entity'];
        }
        if (!empty($filters['ip'])) {
            $where[] = 'al.ip LIKE ?';
            $params[] = '%' . $filters['ip'] . '%';
        }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = '(al.old_value LIKE ? OR al.new_value LIKE ?)';
            $params[] = $q;
            $params[] = $q;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare('
            SELECT al.created_at, u.name AS utente, u.username, al.action, al.entity,
                   al.entity_id, al.ip, al.old_value, al.new_value
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY al.created_at DESC
            LIMIT ' . self::EXPORT_LIMIT . '
        ');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function exportAttempts(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['email'])) {
            $where[] = 'email LIKE ?';
            $params[] = '%' . $filters['email'] . '%';
        }
        if (!empty($filters['ip'])) {
            $where[] = 'ip_address LIKE ?';
            $params[] = '%' . $filters['ip'] . '%';
        }
        if (isset($filters['success']) && $filters['success'] !== '' && $filters['success'] !== null) {
            $where[] = 'success = ?';
            $params[] = (int) $filters['success'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare('
            SELECT created_at, email, ip_address, success
            FROM login_attempts
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY created_at DESC
            LIMIT ' . self::EXPORT_LIMIT . '
        ');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function exportSessions(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 's.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['active_only'])) {
            $where[] = 's.expires_at > NOW()';
        }
        if (!empty($filters['date_from'])) {
            $where[] = 's.last_activity >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 's.last_activity <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare("
            SELECT u.name AS utente, u.username, s.ip, s.user_agent,
                   s.last_activity, s.expires_at,
                   IF(s.expires_at > NOW(), 'attiva', 'scaduta') AS stato
            FROM sessions s
            LEFT JOIN users u ON u.id = s.user_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY s.last_activity DESC
            LIMIT ' . self::EXPORT_LIMIT . '
        ');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
