<?php

declare(strict_types=1);

namespace App\Modules\Teams\Repositories;

use App\Repositories\BaseRepository;

class PresenceRepository extends BaseRepository
{
    protected string $table = 'teams_user_presence';

    /**
     * Aggiorna il heartbeat di presenza.
     *
     * DELETE+INSERT invece di upsert nativo (`ON DUPLICATE KEY UPDATE` è
     * MySQL-only e non ha equivalente diretto in SQLite): stesso stato finale,
     * la finestra di inconsistenza è irrilevante per un heartbeat best-effort
     * richiamato ogni ~10s. Compatibile MariaDB + SQLite (test).
     */
    public function updatePresence(int $userId, ?int $activeConversationId = null): void
    {
        $this->pdo->prepare('DELETE FROM teams_user_presence WHERE user_id = ?')->execute([$userId]);
        $stmt = $this->pdo->prepare(
            'INSERT INTO teams_user_presence (user_id, last_seen_at, active_conversation_id) VALUES (?, NOW(), ?)'
        );
        $stmt->execute([$userId, $activeConversationId]);
    }

    /**
     * Controlla se un utente e' online su Teams (heartbeat entro N secondi).
     *
     * Soglia calcolata lato PHP (non `DATE_SUB(NOW(), INTERVAL ...)`, sintassi
     * MySQL-only non portabile su SQLite) e passata come parametro bind.
     */
    public function isOnline(int $userId, int $thresholdSeconds = 30): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teams_user_presence WHERE user_id = ? AND last_seen_at > ?'
        );
        $stmt->execute([$userId, $this->secondsAgo($thresholdSeconds)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Controlla se un utente sta guardando una specifica conversazione.
     */
    public function isViewingConversation(int $userId, int $conversationId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teams_user_presence
             WHERE user_id = ? AND active_conversation_id = ? AND last_seen_at > ?'
        );
        $stmt->execute([$userId, $conversationId, $this->secondsAgo(15)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Registra che un utente sta digitando in una conversazione.
     */
    public function setTyping(int $userId, int $conversationId): void
    {
        $this->pdo->prepare('DELETE FROM teams_typing WHERE user_id = ? AND conversation_id = ?')
            ->execute([$userId, $conversationId]);
        $stmt = $this->pdo->prepare(
            'INSERT INTO teams_typing (user_id, conversation_id, started_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$userId, $conversationId]);
    }

    /**
     * Utenti che stanno digitando in una conversazione (entro 5 secondi).
     */
    public function getTypingUsers(int $conversationId, int $excludeUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.user_id, u.name
             FROM teams_typing t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.conversation_id = ?
               AND t.user_id != ?
               AND t.started_at > ?'
        );
        $stmt->execute([$conversationId, $excludeUserId, $this->secondsAgo(5)]);
        return $stmt->fetchAll();
    }

    /**
     * Pulizia typing indicators stale (oltre 10 secondi).
     */
    public function cleanupStaleTyping(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM teams_typing WHERE started_at < ?');
        $stmt->execute([$this->secondsAgo(10)]);
    }

    private function secondsAgo(int $seconds): string
    {
        return date('Y-m-d H:i:s', time() - $seconds);
    }
}
