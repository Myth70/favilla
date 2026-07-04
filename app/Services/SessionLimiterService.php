<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ISO 27001 A.9.4.3 — Concurrent session limiter.
 *
 * Enforces a maximum number of concurrent sessions per user.
 * When the limit is exceeded, the oldest session is terminated.
 */
class SessionLimiterService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * Enforce the concurrent session limit for a user.
     * Called after a new session is created (login).
     * Evicts the oldest sessions beyond the configured maximum.
     */
    public function enforce(int $userId, int $currentDbSessionId): void
    {
        $maxSessions = (int) setting('session_max_concurrent', 3);
        if ($maxSessions <= 0) {
            return; // Unlimited sessions
        }

        // Count active sessions for this user
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sessions
             WHERE user_id = ? AND id != ?
             ORDER BY last_activity DESC'
        );
        $stmt->execute([$userId, $currentDbSessionId]);
        $otherSessions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Keep the (maxSessions - 1) most recent, since current is the newest
        $keep = $maxSessions - 1;
        if (count($otherSessions) <= $keep) {
            return; // Within limit
        }

        // Sessions to evict (oldest ones beyond the limit)
        $toEvict = array_slice($otherSessions, $keep);

        if (!empty($toEvict)) {
            $placeholders = implode(',', array_fill(0, count($toEvict), '?'));
            $this->pdo->prepare(
                "DELETE FROM sessions WHERE id IN ({$placeholders}) AND user_id = ?"
            )->execute([...$toEvict, $userId]);

            AuditService::log(
                'session_evicted',
                'session',
                null,
                null,
                ['evicted_count' => count($toEvict), 'reason' => 'concurrent_limit'],
                $userId
            );
        }
    }

    /**
     * Count active sessions for a user.
     */
    public function countActive(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sessions WHERE user_id = ? AND expires_at > NOW()'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
