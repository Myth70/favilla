<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ClientIp;
use PDO;

/**
 * Centralises audit logging to the audit_logs table.
 *
 * Usage (from any controller or service):
 *   AuditService::log('item_created', 'item', $newId, null, $data);
 *   AuditService::log('password_changed', 'user', $userId);
 *
 * Failures are silently swallowed: audit must never abort the main request.
 */
class AuditService
{
    /**
     * Insert a record into audit_logs.
     *
     * @param string      $action    Human-readable action slug  (e.g. 'item_deleted').
     * @param string      $entity    Entity type                 (e.g. 'user', 'item').
     * @param int|null    $entityId  Primary key of the entity (null for system-level events like backups).
     * @param array|null  $oldValue  Previous state as associative array (null if not applicable).
     * @param array|null  $newValue  New state as associative array      (null if not applicable).
     * @param int|null    $userId    Acting user; defaults to the current session user.
     * @param string|null $ip        Client IP; defaults to ClientIp::resolve().
     */
    public static function log(
        string  $action,
        string  $entity,
        int|null $entityId,
        ?array  $oldValue = null,
        ?array  $newValue = null,
        ?int    $userId   = null,
        ?string $ip       = null
    ): void {
        try {
            $pdo     = app(PDO::class);
            $userId  ??= (int) ($_SESSION['user_id'] ?? 0) ?: null;
            $ip      ??= ClientIp::resolve();
            $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
            $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

            $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, entity, entity_id, old_value, new_value, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $action, $entity, $entityId, $oldJson, $newJson, $ip]);
        } catch (\Throwable) {
            // Audit failure must never abort the request.
        }
    }
}
