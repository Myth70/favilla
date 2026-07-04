<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class NotificationDeliveryRepository extends BaseRepository
{
    protected string $table = 'notification_deliveries';
    protected array $fillable = [
        'dispatch_id',
        'user_id',
        'channel_slug',
        'status',
        'subject',
        'body',
        'link',
        'icon',
        'color',
        'provider_message_id',
        'error_message',
        'attempts',
        'sent_at',
    ];
    protected bool $timestamps = true;

    public function createDelivery(array $data): int
    {
        return $this->create($data);
    }

    public function markProcessing(int $deliveryId, int $attempts): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_deliveries SET status = ?, attempts = ? WHERE id = ?'
        );
        $stmt->execute(['processing', $attempts, $deliveryId]);
    }

    public function markSent(int $deliveryId, ?string $providerMessageId = null, ?string $errorMessage = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_deliveries
             SET status = ?, provider_message_id = ?, error_message = ?, sent_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute(['sent', $providerMessageId, $errorMessage, $deliveryId]);
    }

    public function markFailed(int $deliveryId, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_deliveries SET status = ?, error_message = ? WHERE id = ?'
        );
        $stmt->execute(['failed', $errorMessage, $deliveryId]);
    }

    public function markSkipped(int $deliveryId, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_deliveries SET status = ?, error_message = ? WHERE id = ?'
        );
        $stmt->execute(['skipped', $errorMessage, $deliveryId]);
    }

    /**
     * Delivery status counts grouped by channel.
     *
     * @return array<string, array<string, int>>
     */
    public function getStatusCountsByChannel(): array
    {
        $stmt = $this->pdo->query(
            'SELECT channel_slug, status, COUNT(*) AS cnt
             FROM notification_deliveries
             GROUP BY channel_slug, status'
        );

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['channel_slug']][$row['status']] = (int) $row['cnt'];
        }
        return $map;
    }

    /**
     * Reset a delivery status back to pending (used when re-queuing).
     */
    public function resetToPending(int $deliveryId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification_deliveries SET status = ?, error_message = NULL WHERE id = ?'
        );
        $stmt->execute(['pending', $deliveryId]);
    }

    public function getDriverPayload(int $deliveryId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                nd.id AS delivery_id,
                nd.dispatch_id,
                nd.user_id,
                nd.channel_slug,
                nd.subject AS delivery_subject,
                nd.body AS delivery_body,
                nd.link AS delivery_link,
                nd.icon AS delivery_icon,
                nd.color AS delivery_color,
                nd.attempts AS delivery_attempts,
                ds.title AS dispatch_title,
                ds.body AS dispatch_body,
                ds.type AS dispatch_type,
                ds.link AS dispatch_link,
                ds.icon AS dispatch_icon,
                ds.color AS dispatch_color,
                ds.event_slug,
                ds.source_module,
                ds.created_by,
                ds.payload_json
             FROM notification_deliveries nd
             JOIN notification_dispatches ds ON ds.id = nd.dispatch_id
             WHERE nd.id = ?
             LIMIT 1'
        );
        $stmt->execute([$deliveryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
