<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class WebhookDeliveryRepository extends BaseRepository
{
    protected string $table = 'webhook_deliveries';
    protected array $fillable = [
        'endpoint_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'response_code',
        'last_error',
        'next_retry_at',
        'delivered_at',
    ];

    /**
     * Consegne pronte per il tentativo: pending/failed con next_retry_at scaduto
     * (o nullo). Ordine FIFO. Usato dal comando di dispatch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function claimDue(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.*, e.url AS endpoint_url, e.secret AS endpoint_secret, e.is_active AS endpoint_active
             FROM webhook_deliveries d
             INNER JOIN webhook_endpoints e ON e.id = d.endpoint_id
             WHERE d.status = 'pending'
               AND (d.next_retry_at IS NULL OR d.next_retry_at <= NOW())
               AND e.deleted_at IS NULL
             ORDER BY d.id ASC
             LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSent(int $id, int $responseCode): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'sent', attempts = attempts + 1, response_code = ?, last_error = NULL, delivered_at = NOW(), next_retry_at = NULL
             WHERE id = ?"
        );
        $stmt->execute([$responseCode, $id]);
    }

    /**
     * Rilascia per un nuovo tentativo con backoff, oppure segna 'failed' se
     * superati i tentativi massimi.
     */
    public function releaseOrFail(int $id, int $attempts, int $maxAttempts, ?int $responseCode, string $error, int $backoffMinutes): void
    {
        $error = mb_substr($error, 0, 255);
        if ($attempts >= $maxAttempts) {
            $stmt = $this->pdo->prepare(
                "UPDATE webhook_deliveries
                 SET status = 'failed', attempts = ?, response_code = ?, last_error = ?, next_retry_at = NULL
                 WHERE id = ?"
            );
            $stmt->execute([$attempts, $responseCode, $error, $id]);
            return;
        }

        // next_retry_at calcolato in PHP (non DATE_ADD) per portabilità del test
        // su SQLite; il valore è comunque un timestamp assoluto confrontato da
        // claimDue con NOW().
        $nextRetryAt = date('Y-m-d H:i:s', time() + $backoffMinutes * 60);
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'pending', attempts = ?, response_code = ?, last_error = ?, next_retry_at = ?
             WHERE id = ?"
        );
        $stmt->execute([$attempts, $responseCode, $error, $nextRetryAt, $id]);
    }

    /**
     * Ultime consegne di un endpoint (log UI).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentForEndpoint(int $endpointId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhook_deliveries WHERE endpoint_id = ? ORDER BY id DESC LIMIT {$limit}"
        );
        $stmt->execute([$endpointId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{pending:int, sent:int, failed:int}
     */
    public function statusCounts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS c FROM webhook_deliveries GROUP BY status"
        );
        $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    public function purgeOlderThan(int $days): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM webhook_deliveries
             WHERE status IN ('sent','failed') AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
