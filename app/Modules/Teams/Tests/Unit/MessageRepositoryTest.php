<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Repositories\MessageRepository;
use Tests\ModuleTestCase;

/**
 * Test del repository MessageRepository su SQLite in-memory.
 *
 * Copre in particolare `getReactionsForMessages()`: prima della fix usava
 * `GROUP_CONCAT(... ORDER BY ...)` (sintassi MySQL-only, errore di sintassi
 * su SQLite). La riscrittura aggrega lato PHP replicando `getReactions()`:
 * gruppi ordinati per count DESC poi prima-reazione ASC, user_ids in ordine
 * cronologico di reazione all'interno di ciascun gruppo emoji.
 */
class MessageRepositoryTest extends ModuleTestCase
{
    private MessageRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                avatar_path TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_messages (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id    INTEGER NOT NULL,
                user_id            INTEGER NULL,
                body               TEXT NOT NULL,
                type               TEXT NOT NULL DEFAULT "text",
                reply_to_id        INTEGER NULL,
                edited_at          TEXT NULL,
                deleted_at         TEXT NULL,
                pinned_at          TEXT NULL,
                pinned_by          INTEGER NULL,
                state_updated_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                created_at         TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS teams_message_reactions (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                emoji      TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (message_id, user_id, emoji)
            );
            CREATE TABLE IF NOT EXISTS teams_message_attachments (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id    INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                stored_name   TEXT NOT NULL,
                mime_type     TEXT NOT NULL,
                size_bytes    INTEGER NOT NULL DEFAULT 0,
                extension     TEXT NULL,
                created_at    TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS teams_message_mentions (
                message_id         INTEGER NOT NULL,
                mentioned_user_id  INTEGER NOT NULL,
                created_at         TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id, mentioned_user_id)
            );
        ');

        $this->repo = new MessageRepository();

        $this->pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Carlo')");
    }

    private function insertMessage(int $conversationId = 1, int $userId = 1): int
    {
        return $this->insertRow('teams_messages', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'body'            => 'ciao',
        ]);
    }

    private function insertReaction(int $messageId, int $userId, string $emoji, string $createdAt): void
    {
        $this->insertRow('teams_message_reactions', [
            'message_id' => $messageId,
            'user_id'    => $userId,
            'emoji'      => $emoji,
            'created_at' => $createdAt,
        ]);
    }

    // ── getReactionsForMessages: portabilità SQLite (niente GROUP_CONCAT) ──

    public function testGetReactionsForMessagesEmptyInputReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->repo->getReactionsForMessages([]));
    }

    public function testGetReactionsForMessagesDoesNotThrowOnSqlite(): void
    {
        $msgId = $this->insertMessage();
        $this->insertReaction($msgId, 1, '👍', '2026-01-01 10:00:00');

        // Se questo lancia un'eccezione PDO, significa che è tornata la
        // sintassi GROUP_CONCAT MySQL-only non supportata da SQLite.
        $result = $this->repo->getReactionsForMessages([$msgId]);
        $this->assertArrayHasKey($msgId, $result);
    }

    public function testGetReactionsForMessagesGroupsByEmojiWithCountAndUserIds(): void
    {
        $msgId = $this->insertMessage();
        $this->insertReaction($msgId, 1, '👍', '2026-01-01 10:00:00');
        $this->insertReaction($msgId, 2, '👍', '2026-01-01 10:00:05');
        $this->insertReaction($msgId, 3, '❤️', '2026-01-01 10:00:10');

        $result = $this->repo->getReactionsForMessages([$msgId]);

        $this->assertCount(2, $result[$msgId]);

        $thumbsUp = $result[$msgId][0];
        $this->assertSame('👍', $thumbsUp['emoji']);
        $this->assertSame(2, $thumbsUp['count']);
        $this->assertSame([1, 2], $thumbsUp['user_ids']);

        $heart = $result[$msgId][1];
        $this->assertSame('❤️', $heart['emoji']);
        $this->assertSame(1, $heart['count']);
        $this->assertSame([3], $heart['user_ids']);
    }

    public function testGetReactionsForMessagesSortsByCountDescThenFirstReactionAsc(): void
    {
        $msgId = $this->insertMessage();
        // '❤️' reacted first but ends up with fewer reactions than '👍'.
        $this->insertReaction($msgId, 1, '❤️', '2026-01-01 10:00:00');
        $this->insertReaction($msgId, 2, '👍', '2026-01-01 10:00:01');
        $this->insertReaction($msgId, 3, '👍', '2026-01-01 10:00:02');

        $result = $this->repo->getReactionsForMessages([$msgId]);

        // count DESC vince sempre sul first_at: 👍 (2) prima di ❤️ (1).
        $this->assertSame('👍', $result[$msgId][0]['emoji']);
        $this->assertSame('❤️', $result[$msgId][1]['emoji']);
    }

    public function testGetReactionsForMessagesTieBreaksByFirstReactionAsc(): void
    {
        $msgId = $this->insertMessage();
        // Stesso count (1 ciascuna): l'emoji reagita per prima vince il tie-break.
        $this->insertReaction($msgId, 2, '👍', '2026-01-01 10:00:05');
        $this->insertReaction($msgId, 1, '❤️', '2026-01-01 10:00:00');

        $result = $this->repo->getReactionsForMessages([$msgId]);

        $this->assertSame('❤️', $result[$msgId][0]['emoji']);
        $this->assertSame('👍', $result[$msgId][1]['emoji']);
    }

    public function testGetReactionsForMessagesUserIdsAreChronologicalWithinGroup(): void
    {
        $msgId = $this->insertMessage();
        // Inserimento fuori ordine cronologico: user 3 reagisce prima di user 1.
        $this->insertReaction($msgId, 3, '🔥', '2026-01-01 10:00:00');
        $this->insertReaction($msgId, 1, '🔥', '2026-01-01 10:00:05');
        $this->insertReaction($msgId, 2, '🔥', '2026-01-01 10:00:10');

        $result = $this->repo->getReactionsForMessages([$msgId]);

        $this->assertSame([3, 1, 2], $result[$msgId][0]['user_ids']);
    }

    public function testGetReactionsForMessagesBatchesAcrossMultipleMessages(): void
    {
        $msg1 = $this->insertMessage();
        $msg2 = $this->insertMessage();
        $this->insertReaction($msg1, 1, '👍', '2026-01-01 10:00:00');
        $this->insertReaction($msg2, 2, '❤️', '2026-01-01 10:00:00');

        $result = $this->repo->getReactionsForMessages([$msg1, $msg2]);

        $this->assertArrayHasKey($msg1, $result);
        $this->assertArrayHasKey($msg2, $result);
        $this->assertSame('👍', $result[$msg1][0]['emoji']);
        $this->assertSame('❤️', $result[$msg2][0]['emoji']);
    }

    public function testGetReactionsForMessagesMatchesSingleMessageGetReactions(): void
    {
        $msgId = $this->insertMessage();
        $this->insertReaction($msgId, 1, '👍', '2026-01-01 10:00:00');
        $this->insertReaction($msgId, 2, '👍', '2026-01-01 10:00:05');
        $this->insertReaction($msgId, 3, '❤️', '2026-01-01 10:00:10');

        $single = $this->repo->getReactions($msgId);
        $batch  = $this->repo->getReactionsForMessages([$msgId])[$msgId];

        $this->assertSame($single, $batch);
    }

    // ── insertMentions: portabilità SQLite (niente INSERT IGNORE) ──────────

    public function testInsertMentionsDoesNotThrowOnSqlite(): void
    {
        $msgId = $this->insertMessage();

        // Se questo lancia un'eccezione PDO, significa che è tornata la
        // sintassi INSERT IGNORE MySQL-only non supportata da SQLite.
        $this->repo->insertMentions($msgId, [2, 3]);

        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM teams_message_mentions WHERE message_id = {$msgId}")
            ->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function testInsertMentionsDeduplicatesUserIds(): void
    {
        $msgId = $this->insertMessage();

        $this->repo->insertMentions($msgId, [2, 2, 3, 2]);

        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM teams_message_mentions WHERE message_id = {$msgId}")
            ->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function testInsertMentionsEmptyArrayIsNoop(): void
    {
        $msgId = $this->insertMessage();

        $this->repo->insertMentions($msgId, []);

        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM teams_message_mentions WHERE message_id = {$msgId}")
            ->fetchColumn();
        $this->assertSame(0, $count);
    }
}
