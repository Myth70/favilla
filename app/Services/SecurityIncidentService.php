<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Notifications\Services\NotificationService;
use PDO;

/**
 * ISO 27001 A.16.1 — Security incident detection & alerting.
 *
 * Monitors security events and dispatches notifications to admins
 * when thresholds are exceeded.
 */
class SecurityIncidentService
{
    private PDO $pdo;

    /** Default thresholds (overridden by app_settings). */
    private const DEFAULTS = [
        'security_failed_login_threshold' => 10,
        'security_failed_login_window'    => 15,   // minutes
        'security_incident_notify'        => true,
    ];

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    private function setting(string $key): mixed
    {
        return setting($key, self::DEFAULTS[$key] ?? null);
    }

    // ------------------------------------------------------------------
    // Incident detection
    // ------------------------------------------------------------------

    /**
     * Check for brute-force login attempts from a single IP.
     * Called after each failed login (from AuthService or listener).
     */
    public function checkBruteForce(string $ip, string $email): void
    {
        if (!(bool) $this->setting('security_incident_notify')) {
            return;
        }

        $threshold = (int) $this->setting('security_failed_login_threshold');
        $window = (int) $this->setting('security_failed_login_window');

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $window]);
        $count = (int) $stmt->fetchColumn();

        // Fire alert exactly at threshold (not every subsequent attempt)
        if ($count === $threshold) {
            $this->dispatchIncident('brute_force', [
                'ip'       => $ip,
                'email'    => $email,
                'attempts' => $count,
                'window'   => $window,
            ]);

            // Record in security_incidents table
            $this->recordIncident('brute_force', 'high', json_encode([
                'ip' => $ip, 'email' => $email, 'attempts' => $count,
            ], JSON_THROW_ON_ERROR), $ip);
        }
    }

    /**
     * Record a CSRF violation incident.
     */
    public function recordCsrfViolation(string $ip, string $uri): void
    {
        if (!(bool) $this->setting('security_incident_notify')) {
            return;
        }

        $this->recordIncident('csrf_violation', 'medium', json_encode([
            'ip' => $ip, 'uri' => $uri,
        ], JSON_THROW_ON_ERROR), $ip);

        // Count CSRF violations in last 5 minutes
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM security_incidents
             WHERE type = 'csrf_violation' AND ip = ?
             AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $stmt->execute([$ip]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= 5) {
            $this->dispatchIncident('csrf_flood', [
                'ip'      => $ip,
                'uri'     => $uri,
                'count'   => $count,
            ]);
        }
    }

    /**
     * Record a failed authorization attempt (permission denied).
     */
    public function recordAccessDenied(?int $userId, string $route, string $permission, string $ip): void
    {
        $this->recordIncident('access_denied', 'low', json_encode([
            'user_id'    => $userId,
            'route'      => $route,
            'permission' => $permission,
        ], JSON_THROW_ON_ERROR), $ip);

        AuditService::log(
            'access_denied',
            'security',
            null,
            null,
            ['route' => $route, 'permission' => $permission],
            $userId,
            $ip
        );
    }

    // ------------------------------------------------------------------
    // Incident persistence
    // ------------------------------------------------------------------

    /**
     * Record an incident in the security_incidents table.
     */
    public function recordIncident(string $type, string $severity, ?string $details, ?string $ip): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO security_incidents (type, severity, details, ip, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $type,
                $severity,
                $details,
                $ip,
                $_SESSION['user_id'] ?? null,
            ]);
        } catch (\Throwable) {
            // Never let incident logging break the main request
        }
    }

    /**
     * Get recent incidents for the admin dashboard.
     *
     * @return array{items: array, total: int}
     */
    public function getRecent(int $limit = 50, int $offset = 0, ?string $type = null, ?string $severity = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($type) {
            $where[] = 'type = ?';
            $params[] = $type;
        }
        if ($severity) {
            $where[] = 'severity = ?';
            $params[] = $severity;
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM security_incidents WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT si.*, u.name AS user_name
             FROM security_incidents si
             LEFT JOIN users u ON u.id = si.user_id
             WHERE {$whereClause}
             ORDER BY si.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Summary counts by type for last 24h, 7d, 30d.
     */
    public function getSummary(): array
    {
        $periods = [
            '24h' => 'INTERVAL 24 HOUR',
            '7d'  => 'INTERVAL 7 DAY',
            '30d' => 'INTERVAL 30 DAY',
        ];

        $summary = [];
        foreach ($periods as $label => $interval) {
            $stmt = $this->pdo->query(
                "SELECT type, severity, COUNT(*) AS cnt
                 FROM security_incidents
                 WHERE created_at > DATE_SUB(NOW(), {$interval})
                 GROUP BY type, severity
                 ORDER BY cnt DESC"
            );
            $summary[$label] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $summary;
    }

    // ------------------------------------------------------------------
    // Notification dispatch
    // ------------------------------------------------------------------

    /**
     * Dispatch a security incident notification to all admins.
     */
    private function dispatchIncident(string $incidentType, array $context): void
    {
        $typeLabels = [
            'brute_force'            => 'Tentativo di brute-force rilevato',
            'csrf_flood'             => 'Violazioni CSRF multiple rilevate',
            'access_denied'          => 'Accesso non autorizzato',
            'ip_change'              => 'Cambio IP durante sessione attiva',
            'file_integrity_failure' => 'Integrità file compromessa',
        ];

        $title = $typeLabels[$incidentType] ?? 'Incidente di sicurezza';
        $context['incident_type'] = $incidentType;
        $context['incident_title'] = $title;

        try {
            NotificationService::dispatchEventToRole(
                'security.incident_detected',
                'Admin',
                'admin',
                $context,
                route('admin.security.incidents'),
                null,
                'danger',
                'fa-solid fa-shield-exclamation'
            );
        } catch (\Throwable) {
            // Best-effort: security incidents should still be recorded even if notifications fail
        }
    }
}
