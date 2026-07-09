<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class PushSubscriptionRepository extends BaseRepository
{
    protected string $table = 'push_subscriptions';
    protected bool $timestamps = true;
    protected array $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'content_encoding',
        'user_agent',
        'last_used_at',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, endpoint, endpoint_hash, p256dh, auth, content_encoding
             FROM push_subscriptions
             WHERE user_id = ?
             ORDER BY id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Registra o aggiorna la subscription del dispositivo. L'endpoint è la
     * chiave reale (hash sha256 UNIQUE): se già presente — anche per un altro
     * utente, es. browser condiviso con login diverso — viene riassegnata.
     */
    public function upsertForDevice(
        int $userId,
        string $endpoint,
        string $p256dh,
        string $auth,
        string $contentEncoding = 'aes128gcm',
        ?string $userAgent = null
    ): int {
        $hash = hash('sha256', $endpoint);
        $existing = $this->findBy('endpoint_hash', $hash);

        $data = [
            'user_id'          => $userId,
            'endpoint'         => $endpoint,
            'endpoint_hash'    => $hash,
            'p256dh'           => $p256dh,
            'auth'             => $auth,
            'content_encoding' => $contentEncoding,
            'user_agent'       => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ];

        if ($existing !== null) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return $this->create($data);
    }

    /**
     * Rimuove la subscription di un endpoint appartenente all'utente.
     * Restituisce true se una riga è stata eliminata.
     */
    public function deleteForUserByEndpoint(int $userId, string $endpoint): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?'
        );
        $stmt->execute([$userId, hash('sha256', $endpoint)]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Rimozione diretta per hash: usata dal driver quando il push service
     * risponde 404/410 (subscription morta, non è un errore).
     */
    public function deleteByEndpointHash(string $endpointHash): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?');
        $stmt->execute([$endpointHash]);
    }

    /**
     * Totali per il pannello admin.
     *
     * @return array{subscriptions: int, users: int}
     */
    public function stats(): array
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) AS subscriptions, COUNT(DISTINCT user_id) AS users FROM push_subscriptions'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'subscriptions' => (int) ($row['subscriptions'] ?? 0),
            'users'         => (int) ($row['users'] ?? 0),
        ];
    }

    /**
     * @param int[] $ids
     */
    public function touchLastUsed(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE push_subscriptions SET last_used_at = NOW() WHERE id IN ({$placeholders})"
        );
        $stmt->execute($ids);
    }
}
