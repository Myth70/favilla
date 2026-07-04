<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class NotificationQueueRepository extends BaseRepository
{
    protected string $table = 'notification_queue';
    protected array $fillable = [
        'delivery_id',
        'channel_slug',
        'payload_json',
        'status',
        'available_at',
        'locked_at',
        'attempts',
        'max_attempts',
        'last_error',
    ];
    protected bool $timestamps = true;

    public function enqueue(int $deliveryId, string $channelSlug, array $payload = [], int $maxAttempts = 5): int
    {
        return $this->create([
            'delivery_id'  => $deliveryId,
            'channel_slug' => $channelSlug,
            'payload_json' => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'status'       => 'pending',
            'available_at' => date('Y-m-d H:i:s'),
            'locked_at'    => null,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'last_error'   => null,
        ]);
    }

    public function claimPending(int $limit = 25, ?string $channel = null): array
    {
        $limit = max(1, $limit);
        $params = [];
        $where = 'q.status = ? AND q.available_at <= NOW()';
        $params[] = 'pending';

        if ($channel !== null && $channel !== '') {
            $where .= ' AND q.channel_slug = ?';
            $params[] = $channel;
        }

        $stmt = $this->pdo->prepare(
            "SELECT q.id
             FROM notification_queue q
             WHERE {$where}
             ORDER BY q.available_at ASC, q.id ASC
             {$this->limitClause($limit)}"
        );
        $stmt->execute($params);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $claimed = [];
        foreach ($ids as $id) {
            $claim = $this->pdo->prepare(
                'UPDATE notification_queue
                 SET status = ?, locked_at = NOW(), attempts = attempts + 1
                 WHERE id = ? AND status = ?'
            );
            $claim->execute(['processing', (int) $id, 'pending']);

            if ($claim->rowCount() > 0) {
                $row = $this->findJobByQueueId((int) $id);
                if ($row) {
                    $claimed[] = $row;
                }
            }
        }

        return $claimed;
    }

    public function markSent(int $queueId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_queue SET status = ?, locked_at = NULL, last_error = NULL WHERE id = ?'
        );
        $stmt->execute(['sent', $queueId]);
    }

    public function markSkipped(int $queueId, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_queue SET status = ?, locked_at = NULL, last_error = ? WHERE id = ?'
        );
        $stmt->execute(['skipped', $errorMessage, $queueId]);
    }

    public function releaseForRetry(int $queueId, int $attempts, string $errorMessage): void
    {
        $delaySeconds = min(900, max(30, $attempts * 60));
        $nextRun = date('Y-m-d H:i:s', time() + $delaySeconds);

        $stmt = $this->pdo->prepare(
            'UPDATE notification_queue
             SET status = ?, locked_at = NULL, available_at = ?, last_error = ?
             WHERE id = ?'
        );
        $stmt->execute(['pending', $nextRun, $errorMessage, $queueId]);
    }

    public function markFailed(int $queueId, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_queue SET status = ?, locked_at = NULL, last_error = ? WHERE id = ?'
        );
        $stmt->execute(['failed', $errorMessage, $queueId]);
    }

    /**
     * Recent queue rows with delivery/dispatch/user details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentWithDetails(int $limit = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                q.id,
                q.channel_slug,
                q.status,
                q.attempts,
                q.max_attempts,
                q.available_at,
                q.last_error,
                d.user_id,
                ds.title,
                ds.source_module,
                u.name AS user_name
             FROM notification_queue q
             JOIN notification_deliveries d ON d.id = q.delivery_id
             JOIN notification_dispatches ds ON ds.id = d.dispatch_id
             LEFT JOIN users u ON u.id = d.user_id
             ORDER BY q.updated_at DESC, q.id DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Queue status counts grouped by channel.
     *
     * @return array<string, array<string, int>>
     */
    public function getStatusCountsByChannel(): array
    {
        $stmt = $this->pdo->query(
            'SELECT channel_slug, status, COUNT(*) AS cnt
             FROM notification_queue
             GROUP BY channel_slug, status'
        );

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['channel_slug']][$row['status']] = (int) $row['cnt'];
        }
        return $map;
    }

    /**
     * Reset a failed queue item back to pending for retry.
     */
    public function resetToRetry(int $queueId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_queue
             SET status = ?, locked_at = NULL, available_at = NOW(), last_error = NULL
             WHERE id = ? AND status = ?'
        );
        $stmt->execute(['pending', $queueId, 'failed']);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reset ALL failed queue items back to pending.
     *
     * @return int Number of items reset.
     */
    public function resetAllFailedToRetry(): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_queue
             SET status = ?, locked_at = NULL, available_at = NOW(), last_error = NULL
             WHERE status = ?'
        );
        $stmt->execute(['pending', 'failed']);
        return $stmt->rowCount();
    }

    /**
     * Get the delivery_id for a queue item.
     */
    public function getDeliveryId(int $queueId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT delivery_id FROM notification_queue WHERE id = ? LIMIT 1');
        $stmt->execute([$queueId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : null;
    }

    private function findJobByQueueId(int $queueId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                q.id AS queue_id,
                q.delivery_id,
                q.channel_slug,
                q.payload_json AS queue_payload_json,
                q.attempts AS queue_attempts,
                q.max_attempts,
                nd.dispatch_id,
                nd.user_id,
                nd.subject AS delivery_subject,
                nd.body AS delivery_body,
                nd.link AS delivery_link,
                nd.icon AS delivery_icon,
                nd.color AS delivery_color,
                ds.title AS dispatch_title,
                ds.body AS dispatch_body,
                ds.type AS dispatch_type,
                ds.link AS dispatch_link,
                ds.icon AS dispatch_icon,
                ds.color AS dispatch_color,
                ds.event_slug,
                ds.source_module,
                ds.created_by,
                ds.payload_json AS dispatch_payload_json
             FROM notification_queue q
             JOIN notification_deliveries nd ON nd.id = q.delivery_id
             JOIN notification_dispatches ds ON ds.id = nd.dispatch_id
             WHERE q.id = ?
             LIMIT 1'
        );
        $stmt->execute([$queueId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
