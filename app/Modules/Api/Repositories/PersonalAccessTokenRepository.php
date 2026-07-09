<?php

declare(strict_types=1);

namespace App\Modules\Api\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class PersonalAccessTokenRepository extends BaseRepository
{
    protected string $table = 'personal_access_tokens';
    protected bool $timestamps = true;
    protected array $fillable = [
        'user_id',
        'name',
        'token_hash',
        'scopes',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    /**
     * Risolve un token in chiaro (per hash) a una riga valida: non revocato e
     * non scaduto. NULL se assente/revocato/scaduto.
     *
     * @return array<string, mixed>|null
     */
    public function findValidByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personal_access_tokens
             WHERE token_hash = ?
               AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Token di un utente (esclude i revocati), ordinati per creazione discendente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personal_access_tokens
             WHERE user_id = ? AND revoked_at IS NULL
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personal_access_tokens WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markRevoked(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE personal_access_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$id]);
    }

    /**
     * Aggiorna last_used_at (best-effort, chiamato a ogni richiesta autenticata).
     */
    public function touchLastUsed(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE personal_access_tokens SET last_used_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    /**
     * Purga i token revocati o scaduti da oltre $days giorni. Usato dal
     * comando di cleanup periodico. Restituisce il numero di righe rimosse.
     */
    public function purgeStale(int $days = 30): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM personal_access_tokens
             WHERE (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL ? DAY))
                OR (expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL ? DAY))'
        );
        $stmt->execute([$days, $days]);
        return $stmt->rowCount();
    }
}
