<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\ModuleLoader;
use PDO;

class AdminDashboardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    public function getStats(): array
    {
        $q = fn (string $sql) => (function () use ($sql): int {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([]);
            return (int) $stmt->fetchColumn();
        })();

        return [
            'total_users'         => (int) $q('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'active_users'        => (int) $q('SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL'),
            'inactive_users'      => (int) $q('SELECT COUNT(*) FROM users WHERE is_active = 0 AND deleted_at IS NULL'),
            'roles_count'         => (int) $q('SELECT COUNT(*) FROM roles'),
            'active_sessions'     => (int) $q('SELECT COUNT(*) FROM sessions WHERE expires_at > NOW()'),
            'today_logins'        => (int) $q("SELECT COUNT(*) FROM audit_logs WHERE action = 'login' AND DATE(created_at) = CURDATE()"),
            'yesterday_logins'    => (int) $q("SELECT COUNT(*) FROM audit_logs WHERE action = 'login' AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"),
            'new_users_week'      => (int) $q('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL'),
            'total_audit'         => (int) $q('SELECT COUNT(*) FROM audit_logs'),
            'modules_count'       => count(app(ModuleLoader::class)->getModules()),
            'failed_logins_today' => (int) $q('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND DATE(created_at) = CURDATE()'),
        ];
    }

    public function getAuditTypeDistribution(int $days = 7): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT action, COUNT(*) AS total
             FROM audit_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY action
             ORDER BY total DESC
             LIMIT 8'
        );
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $labelMap = [
            'login'                 => 'Accesso',
            'logout'                => 'Disconnessione',
            'password_reset'        => 'Reset pw',
            'password_changed'      => 'Cambio pw',
            'password_forgot_reset' => 'Pw dimenticata',
            'user_disabled'         => 'Disabilitato',
            'create'                => 'Creazione',
            'update'                => 'Modifica',
            'delete'                => 'Eliminazione',
        ];

        $result = ['labels' => [], 'values' => []];
        foreach ($rows as $row) {
            $result['labels'][] = $labelMap[$row['action']] ?? ucfirst($row['action']);
            $result['values'][] = (int) $row['total'];
        }
        return $result;
    }

    public function getRecentLogs(int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.action, a.entity, a.entity_id, a.ip, a.created_at,
                    COALESCE(u.name, 'Sistema') AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Timeline unificata: audit_logs + login_attempts falliti, mescolati per data.
     */
    public function getUnifiedTimeline(int $limit = 20): array
    {
        // Audit log
        $stmt = $this->pdo->prepare(
            "SELECT 'audit' AS source, a.action, a.entity, a.entity_id, a.ip, a.created_at,
                    COALESCE(u.name, 'Sistema') AS user_name, NULL AS detail
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        $audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Login falliti (segnale di sicurezza)
        $failLimit = max(1, intdiv($limit, 2));
        $stmt2 = $this->pdo->prepare(
            "SELECT 'login_fail' AS source, 'login_failed' AS action,
                    'user' AS entity, NULL AS entity_id, ip_address AS ip, created_at,
                    '' AS user_name, email AS detail
             FROM login_attempts
             WHERE success = 0
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt2->execute([$failLimit]);
        $fails = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Merge e ordina per data decrescente
        $merged = array_merge($audit, $fails);
        usort($merged, static fn ($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        return array_slice($merged, 0, $limit);
    }

    public function getLoginChartData(int $days = 7): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM audit_logs
             WHERE action = 'login'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lookup = [];
        foreach ($rows as $row) {
            $lookup[$row['day']] = (int) $row['total'];
        }

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('d/m', strtotime($date));
            $values[] = $lookup[$date] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function getModuleStatus(): array
    {
        return app(ModuleLoader::class)->getAllModulesWithStatus($this->pdo);
    }

    public function getSystemInfo(): array
    {
        return [
            'php_version'  => PHP_VERSION,
            'server'       => $_SERVER['SERVER_SOFTWARE'] ?? 'N/D',
            'environment'  => config('app.env', 'production'),
            'debug_mode'   => config('app.debug') ? 'Attivo' : 'Disattivo',
            'session_life' => config('app.session.lifetime', 480) . ' min',
            'timezone'     => date_default_timezone_get(),
            'db_version'   => $this->getDbVersion(),
        ];
    }

    private function getDbVersion(): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT VERSION()');
            $stmt->execute([]);
            return $stmt->fetchColumn() ?: 'N/D';
        } catch (\Throwable $e) {
            return 'N/D';
        }
    }

    /**
     * Dati grafico sicurezza login: accessi OK vs falliti per giorno (ultimi N giorni).
     * Riempie i giorni senza dati con zero.
     *
     * @return array{labels: string[], ok_values: int[], fail_values: int[]}
     */
    public function getLoginSecurityChartData(int $days = 14): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS day,
                    SUM(success = 1)  AS ok_count,
                    SUM(success = 0)  AS fail_count
             FROM login_attempts
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC'
        );
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) {
            $map[$r['day']] = $r;
        }

        $result = ['labels' => [], 'ok_values' => [], 'fail_values' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result['labels'][]      = date('d/m', strtotime($d));
            $result['ok_values'][]   = isset($map[$d]) ? (int) $map[$d]['ok_count'] : 0;
            $result['fail_values'][] = isset($map[$d]) ? (int) $map[$d]['fail_count'] : 0;
        }
        return $result;
    }

    /**
     * Top N utenti più attivi negli ultimi N giorni (per conteggio audit log).
     *
     * @return array<array{name: string, avatar_path: string|null, action_count: int}>
     */
    public function getTopActiveUsers(int $limit = 5, int $days = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.name, u.avatar_path, COUNT(*) AS action_count
             FROM audit_logs a
             JOIN users u ON a.user_id = u.id
             WHERE u.deleted_at IS NULL
               AND a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY a.user_id, u.name, u.avatar_path
             ORDER BY action_count DESC
             LIMIT ?'
        );
        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Utenti con sessione attiva in questo momento (expires_at > NOW()).
     *
     * @return array<array{name: string, avatar_path: string|null, ip: string, last_activity: string}>
     */
    public function getOnlineSessions(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.name, u.avatar_path, s.ip, s.last_activity
             FROM sessions s
             JOIN users u ON s.user_id = u.id
             WHERE s.expires_at > NOW()
             ORDER BY s.last_activity DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
