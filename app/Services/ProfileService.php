<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class ProfileService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * Get active sessions for a user (not expired).
     */
    public function getActiveSessions(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, ip, user_agent, last_activity, expires_at
             FROM sessions
             WHERE user_id = ? AND expires_at > NOW()
             ORDER BY last_activity DESC'
        );
        $stmt->execute([$userId]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sessions as &$s) {
            $s['parsed_ua'] = $this->parseUserAgent($s['user_agent'] ?? '');
        }

        return $sessions;
    }

    /**
     * Revoke (delete) a session belonging to the user.
     * Returns true if a row was deleted, false otherwise.
     */
    public function revokeSession(int $userId, int $sessionId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$sessionId, $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke ALL active sessions for a user (admin force-logout).
     * Returns the number of sessions deleted.
     */
    public function revokeAllSessions(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }

    /**
     * Get recent login attempts for the user's email.
     */
    public function getLoginHistory(string $email, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ip_address, success, created_at
             FROM login_attempts
             WHERE email = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$email, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent audit log entries for the user.
     */
    public function getRecentActivity(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT action, entity, entity_id, ip, created_at
             FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['meta'] = $this->getActionMeta($row['action']);
        }

        return $rows;
    }

    /**
     * Get account statistics (counts from various modules).
     */
    public function getAccountStats(int $userId, string $email): array
    {
        $stats = [];

        // Days since registration
        $stmt = $this->pdo->prepare(
            'SELECT DATEDIFF(NOW(), created_at) AS days FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['days_registered'] = (int) ($row['days'] ?? 0);

        // Total successful logins
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM login_attempts WHERE email = ? AND success = 1'
        );
        $stmt->execute([$email]);
        $stats['total_logins'] = (int) $stmt->fetchColumn();

        // Files uploaded (if table exists)
        if ($this->tableExists('files')) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS cnt FROM files WHERE created_by = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$userId]);
            $stats['files_uploaded'] = (int) $stmt->fetchColumn();
        }

        return $stats;
    }

    /**
     * Parse user agent string into browser/OS info.
     */
    public function parseUserAgent(string $ua): array
    {
        $browser = 'Browser sconosciuto';
        $browserIcon = 'fa-globe';
        $os = '';

        // Browser detection
        if (preg_match('/Edg(?:e|A)?\/[\d.]+/', $ua)) {
            $browser = 'Microsoft Edge';
            $browserIcon = 'fa-brands fa-edge';
        } elseif (preg_match('/OPR\/[\d.]+|Opera\/[\d.]+/', $ua)) {
            $browser = 'Opera';
            $browserIcon = 'fa-brands fa-opera';
        } elseif (preg_match('/Chrome\/[\d.]+/', $ua) && !preg_match('/Chromium/', $ua)) {
            $browser = 'Google Chrome';
            $browserIcon = 'fa-brands fa-chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $ua)) {
            $browser = 'Mozilla Firefox';
            $browserIcon = 'fa-brands fa-firefox-browser';
        } elseif (preg_match('/Safari\/[\d.]+/', $ua) && !preg_match('/Chrome/', $ua)) {
            $browser = 'Safari';
            $browserIcon = 'fa-brands fa-safari';
        }

        // OS detection
        if (preg_match('/Windows NT 10/', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Windows NT/', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS X/', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/', $ua) && !preg_match('/Android/', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/', $ua)) {
            $os = 'iOS';
        }

        return [
            'browser'     => $browser,
            'browser_icon' => $browserIcon,
            'os'          => $os,
        ];
    }

    /**
     * Get display metadata for an audit action.
     */
    private function getActionMeta(string $action): array
    {
        $map = [
            'login'              => ['label' => 'Accesso effettuato',       'icon' => 'fa-solid fa-right-to-bracket',   'color' => 'success'],
            'logout'             => ['label' => 'Disconnessione',           'icon' => 'fa-solid fa-right-from-bracket', 'color' => 'secondary'],
            'password_changed'   => ['label' => 'Password modificata',      'icon' => 'fa-solid fa-key',                'color' => 'warning'],
            'profile_updated'    => ['label' => 'Profilo aggiornato',       'icon' => 'fa-solid fa-user-pen',           'color' => 'info'],
            'avatar_uploaded'    => ['label' => 'Foto profilo aggiornata',  'icon' => 'fa-solid fa-camera',             'color' => 'info'],
            'avatar_removed'     => ['label' => 'Foto profilo rimossa',     'icon' => 'fa-solid fa-camera-slash',       'color' => 'secondary'],
            'user_created'       => ['label' => 'Utente creato',            'icon' => 'fa-solid fa-user-plus',          'color' => 'success'],
            'user_updated'       => ['label' => 'Utente modificato',        'icon' => 'fa-solid fa-user-pen',           'color' => 'info'],
            'user_deleted'       => ['label' => 'Utente eliminato',         'icon' => 'fa-solid fa-user-minus',         'color' => 'danger'],
            'item_created'       => ['label' => 'Elemento creato',          'icon' => 'fa-solid fa-plus',               'color' => 'success'],
            'item_updated'       => ['label' => 'Elemento modificato',      'icon' => 'fa-solid fa-pen',                'color' => 'info'],
            'item_deleted'       => ['label' => 'Elemento eliminato',       'icon' => 'fa-solid fa-trash',              'color' => 'danger'],
            'file_uploaded'      => ['label' => 'File caricato',            'icon' => 'fa-solid fa-cloud-arrow-up',     'color' => 'info'],
            'file_deleted'       => ['label' => 'File eliminato',           'icon' => 'fa-solid fa-file-circle-minus',  'color' => 'danger'],
            'backup_created'     => ['label' => 'Backup creato',            'icon' => 'fa-solid fa-database',           'color' => 'success'],
            'settings_updated'   => ['label' => 'Impostazioni aggiornate', 'icon' => 'fa-solid fa-gear',               'color' => 'info'],
            'notification_preferences_updated' => ['label' => 'Preferenze notifiche aggiornate', 'icon' => 'fa-solid fa-bell', 'color' => 'info'],
            'notification_bindings_updated' => ['label' => 'Binding notifiche aggiornati', 'icon' => 'fa-solid fa-diagram-project', 'color' => 'info'],
            'notification_bot_updated' => ['label' => 'Bot Telegram aggiornato', 'icon' => 'fa-brands fa-telegram', 'color' => 'info'],
            'notification_bot_created' => ['label' => 'Bot Telegram creato', 'icon' => 'fa-brands fa-telegram', 'color' => 'success'],
            'notification_telegram_linked' => ['label' => 'Telegram collegato', 'icon' => 'fa-brands fa-telegram', 'color' => 'success'],
            'notification_telegram_disconnected' => ['label' => 'Telegram scollegato', 'icon' => 'fa-solid fa-link-slash', 'color' => 'warning'],
            'notification_telegram_token_regenerated' => ['label' => 'Token Telegram rigenerato', 'icon' => 'fa-solid fa-rotate', 'color' => 'info'],
            'role_updated'       => ['label' => 'Ruolo modificato',         'icon' => 'fa-solid fa-shield-halved',      'color' => 'warning'],
            'session_revoked'    => ['label' => 'Sessione revocata',        'icon' => 'fa-solid fa-plug-circle-xmark',  'color' => 'warning'],
        ];

        if (isset($map[$action])) {
            return $map[$action];
        }

        // Fallback: humanize action slug
        $label = ucfirst(str_replace('_', ' ', $action));
        return ['label' => $label, 'icon' => 'fa-solid fa-circle-dot', 'color' => 'secondary'];
    }

    /**
     * Check if a table exists in the database.
     */
    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
