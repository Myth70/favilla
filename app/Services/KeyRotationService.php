<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ISO 27001 A.10.1.2 — Key Rotation management service.
 *
 * Tracks and reminds about rotation of cryptographic keys:
 * - APP_KEY (field encryption, CSRF, log HMAC)
 * - BACKUP_ENCRYPTION_KEY (backup encryption)
 *
 * Key rotation status and last-rotation timestamps are stored in app_settings.
 */
class KeyRotationService
{
    /**
     * Default rotation interval: 180 days (6 months).
     */
    private const DEFAULT_MAX_AGE_DAYS = 180;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * Get rotation status for all tracked keys.
     *
     * @return array [ ['key' => ..., 'last_rotated' => ..., 'age_days' => ..., 'overdue' => bool, 'max_age' => int], ... ]
     */
    public function getStatus(): array
    {
        $maxAge = (int) setting('key_rotation_max_days', self::DEFAULT_MAX_AGE_DAYS);

        $keys = [
            [
                'key'       => 'APP_KEY',
                'setting'   => 'key_rotation_app_key_last',
                'purpose'   => 'Crittografia campi, CSRF, HMAC log',
                'present'   => strlen(env('APP_KEY', '')) >= 32,
            ],
            [
                'key'       => 'BACKUP_ENCRYPTION_KEY',
                'setting'   => 'key_rotation_backup_key_last',
                'purpose'   => 'Crittografia backup database',
                'present'   => strlen(env('BACKUP_ENCRYPTION_KEY', '')) >= 16,
            ],
        ];

        $results = [];
        foreach ($keys as $k) {
            $lastRotated = setting($k['setting'], null);
            $lastDate    = $lastRotated ? strtotime($lastRotated) : null;
            $ageDays     = $lastDate ? (int) ((time() - $lastDate) / 86400) : null;
            $overdue     = $ageDays === null || $ageDays > $maxAge;

            $results[] = [
                'key'          => $k['key'],
                'purpose'      => $k['purpose'],
                'present'      => $k['present'],
                'last_rotated' => $lastRotated,
                'age_days'     => $ageDays,
                'max_age_days' => $maxAge,
                'overdue'      => $overdue,
            ];
        }

        return $results;
    }

    /**
     * Record that a key has been rotated (call after manual rotation).
     */
    public function recordRotation(string $keyName): void
    {
        $settingMap = [
            'APP_KEY'               => 'key_rotation_app_key_last',
            'BACKUP_ENCRYPTION_KEY' => 'key_rotation_backup_key_last',
        ];

        $setting = $settingMap[$keyName] ?? null;
        if ($setting === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Use SettingsService if available, otherwise raw SQL
        try {
            $svc = app(\App\Services\SettingsService::class);
            $svc->set($setting, $now);
        } catch (\Throwable) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO app_settings (`key`, `value`, `group`, updated_at)
                 VALUES (?, ?, 'security', NOW())
                 ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()"
            );
            $stmt->execute([$setting, $now, $now]);
        }

        AuditService::log('key_rotated', 'system', null, null, [
            'key_name' => $keyName,
        ]);
    }

    /**
     * Check if any key is overdue for rotation.
     *
     * @return bool True if at least one key needs rotation
     */
    public function hasOverdueKeys(): bool
    {
        foreach ($this->getStatus() as $s) {
            if ($s['overdue'] && $s['present']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send rotation reminder notifications to admins if keys are overdue.
     */
    public function sendReminders(): int
    {
        $sent = 0;
        foreach ($this->getStatus() as $s) {
            if (!$s['overdue'] || !$s['present']) {
                continue;
            }

            $days = $s['age_days'] !== null ? "{$s['age_days']} giorni" : 'mai ruotata';
            try {
                \App\Modules\Notifications\Services\NotificationService::sendToRole(
                    'admin',
                    'Rotazione chiave necessaria',
                    "La chiave {$s['key']} è scaduta ({$days}). Rotazione raccomandata ogni {$s['max_age_days']} giorni.",
                    'warning',
                    route('admin.security.keys'),
                    null
                );
                $sent++;
            } catch (\Throwable $e) {
                app_log('error', self::class . ': key rotation notification failed: ' . $e->getMessage());
            }
        }
        return $sent;
    }
}
