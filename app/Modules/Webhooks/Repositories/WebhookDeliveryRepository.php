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
        'locked_at',
    ];

    /** Dopo quanti minuti una riga rimasta 'processing' (worker crashato) viene recuperata. */
    private const STALE_LOCK_MINUTES = 15;

    /**
     * Reclama atomicamente le consegne dovute per l'invio. Ogni riga passa a
     * 'processing' con un claim per-riga (`WHERE id=? AND status='pending'`),
     * così due run concorrenti del dispatcher non possono prendere la stessa
     * consegna e inviarla due volte. Solo endpoint attivi e non soft-deleted.
     * Ordine FIFO.
     *
     * @return array<int, array<string, mixed>>
     */
    public function claimDue(int $limit): array
    {
        // Recupera le righe bloccate da un worker morto prima di selezionare.
        $this->reapStale(self::STALE_LOCK_MINUTES);

        $candidates = $this->pdo->prepare(
            "SELECT d.id
             FROM webhook_deliveries d
             INNER JOIN webhook_endpoints e ON e.id = d.endpoint_id
             WHERE d.status = 'pending'
               AND (d.next_retry_at IS NULL OR d.next_retry_at <= NOW())
               AND e.is_active = 1
               AND e.deleted_at IS NULL
             ORDER BY d.id ASC
             LIMIT {$limit}"
        );
        $candidates->execute();
        $ids = $candidates->fetchAll(PDO::FETCH_COLUMN);
        if ($ids === []) {
            return [];
        }

        // Claim atomico per riga: vince solo il processo che trova la riga ancora
        // 'pending'. Portabile (nessun FOR UPDATE / SKIP LOCKED, assenti su SQLite).
        $claim = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'processing', locked_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $claimed = [];
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            $claim->execute([$id]);
            if ($claim->rowCount() === 1) {
                $claimed[] = $id;
            }
        }
        if ($claimed === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($claimed), '?'));
        $rows = $this->pdo->prepare(
            "SELECT d.*, e.url AS endpoint_url, e.secret AS endpoint_secret
             FROM webhook_deliveries d
             INNER JOIN webhook_endpoints e ON e.id = d.endpoint_id
             WHERE d.id IN ({$placeholders})
             ORDER BY d.id ASC"
        );
        $rows->execute($claimed);
        return $rows->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Riporta a 'pending' le consegne rimaste 'processing' oltre la soglia
     * (worker terminato senza rilasciare il lock). Ritorna il numero recuperato.
     */
    public function reapStale(int $minutes): int
    {
        // Cutoff calcolato in PHP per portabilità SQLite (niente DATE_SUB/INTERVAL).
        $cutoff = date('Y-m-d H:i:s', time() - $minutes * 60);
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'pending', locked_at = NULL
             WHERE status = 'processing' AND (locked_at IS NULL OR locked_at < ?)"
        );
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    public function markSent(int $id, int $responseCode): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'sent', attempts = attempts + 1, response_code = ?, last_error = NULL, delivered_at = NOW(), next_retry_at = NULL, locked_at = NULL
             WHERE id = ?"
        );
        $stmt->execute([$responseCode, $id]);
    }

    /**
     * Rilascia una consegna per un nuovo tentativo SENZA incrementare i tentativi
     * né avvicinarla a 'failed'. Per i fallimenti pre-connessione (SSRF/DNS
     * transitorio) che non sono colpa dell'endpoint.
     */
    public function release(int $id, ?int $responseCode, string $error, int $backoffMinutes): void
    {
        $error = mb_substr($error, 0, 255);
        $nextRetryAt = date('Y-m-d H:i:s', time() + $backoffMinutes * 60);
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'pending', response_code = ?, last_error = ?, next_retry_at = ?, locked_at = NULL
             WHERE id = ?"
        );
        $stmt->execute([$responseCode, $error, $nextRetryAt, $id]);
    }

    /**
     * Segna 'failed' le consegne ancora in coda (pending/processing) di un
     * endpoint: usato quando l'endpoint viene eliminato, così non restano orfane
     * (il soft-delete non fa scattare la CASCADE della FK).
     */
    public function failPendingForEndpoint(int $endpointId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_deliveries
             SET status = 'failed', last_error = 'Endpoint eliminato', next_retry_at = NULL, locked_at = NULL
             WHERE endpoint_id = ? AND status IN ('pending', 'processing')"
        );
        $stmt->execute([$endpointId]);
        return $stmt->rowCount();
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
                 SET status = 'failed', attempts = ?, response_code = ?, last_error = ?, next_retry_at = NULL, locked_at = NULL
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
             SET status = 'pending', attempts = ?, response_code = ?, last_error = ?, next_retry_at = ?, locked_at = NULL
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
            'SELECT status, COUNT(*) AS c FROM webhook_deliveries GROUP BY status'
        );
        $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    // Nota: la pulizia periodica delle consegne completate ('sent'/'failed') è a
    // carico del comando schedulato `cleanup` (app/Cli/Commands/CleanupCommand.php,
    // job scheduler 'cleanup'), unica fonte per tutte le tabelle a retention.
}
