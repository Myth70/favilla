<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Controllers\MessageController;
use Tests\ControllerTestCase;

class MessageControllerTest extends ControllerTestCase
{
    private int $conv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                avatar_path TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                type        TEXT NOT NULL DEFAULT "direct",
                name        TEXT NULL,
                created_by  INTEGER NULL,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                archived_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_conversation_members (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id     INTEGER NOT NULL,
                user_id             INTEGER NOT NULL,
                role                TEXT NOT NULL DEFAULT "member",
                joined_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                last_read_at        TEXT NULL,
                notifications_muted INTEGER NOT NULL DEFAULT 0,
                hidden_at           TEXT NULL,
                left_at             TEXT NULL,
                UNIQUE (conversation_id, user_id)
            );
            CREATE TABLE IF NOT EXISTS teams_messages (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id  INTEGER NOT NULL,
                user_id          INTEGER NULL,
                reply_to_id      INTEGER NULL,
                body             TEXT NOT NULL,
                type             TEXT NOT NULL DEFAULT "text",
                pinned_at        TEXT NULL,
                pinned_by        INTEGER NULL,
                state_updated_at TEXT NULL,
                edited_at        TEXT NULL,
                deleted_at       TEXT NULL,
                created_at       TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS teams_message_edits (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                old_body   TEXT NOT NULL,
                edited_by  INTEGER NULL,
                edited_at  TEXT DEFAULT CURRENT_TIMESTAMP
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
                message_id        INTEGER NOT NULL,
                mentioned_user_id INTEGER NOT NULL,
                created_at        TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id, mentioned_user_id)
            );
            CREATE TABLE IF NOT EXISTS teams_message_reactions (
                message_id INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                emoji      TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id, user_id, emoji)
            );
            CREATE TABLE IF NOT EXISTS teams_user_presence (
                user_id                INTEGER NOT NULL PRIMARY KEY,
                last_seen_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                active_conversation_id INTEGER NULL
            );
        ');

        $this->insertRow('users', ['name' => 'Alice']);
        $this->insertRow('users', ['name' => 'Bob']);

        $this->conv = $this->insertRow('teams_conversations', ['type' => 'direct', 'created_by' => 1]);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 1, 'role' => 'admin']);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 2, 'role' => 'member']);

        $this->actingAs(1, ['teams.view', 'teams.create', 'teams.delete']);
        $_SESSION['user_name'] = 'Alice';
    }

    private function insertMessage(int $userId, string $body): int
    {
        return $this->insertRow('teams_messages', [
            'conversation_id' => $this->conv,
            'user_id'         => $userId,
            'body'            => $body,
        ]);
    }

    public function testIndexReturnsOlderMessages(): void
    {
        $first = $this->insertMessage(1, 'primo');
        $second = $this->insertMessage(2, 'secondo');

        $result = $this->withGet(['before' => (string) $second])
            ->dispatch(MessageController::class, 'index', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['messages']);
    }

    public function testStoreCreatesMessageAndRendersBubble(): void
    {
        $result = $this->withPost(['body' => 'Ciao a tutti'])
            ->dispatch(MessageController::class, 'store', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_messages WHERE conversation_id = {$this->conv} AND body = 'Ciao a tutti'")->fetchColumn()
        );
    }

    public function testStoreRejectsEmptyBodyWithoutAttachment(): void
    {
        $result = $this->withPost(['body' => ''])
            ->dispatch(MessageController::class, 'store', [(string) $this->conv]);

        $this->assertSame(422, http_response_code());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM teams_messages')->fetchColumn());
    }

    public function testUpdateEditsMessageAndRendersBubble(): void
    {
        $msgId = $this->insertMessage(1, 'originale');

        $result = $this->withPost(['body' => 'modificato'])
            ->dispatch(MessageController::class, 'update', [(string) $this->conv, (string) $msgId]);

        $this->assertTrue($result->didRender());
        $this->assertSame('modificato', $this->pdo->query("SELECT body FROM teams_messages WHERE id = {$msgId}")->fetchColumn());
    }

    public function testHistoryReturnsEditHistory(): void
    {
        $msgId = $this->insertMessage(1, 'v2');
        $this->insertRow('teams_message_edits', ['message_id' => $msgId, 'old_body' => 'v1', 'edited_by' => 1]);

        $result = $this->dispatch(MessageController::class, 'history', [(string) $this->conv, (string) $msgId]);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['edits']);
    }

    public function testTogglePinPinsMessage(): void
    {
        $msgId = $this->insertMessage(2, 'importante');

        $result = $this->dispatch(MessageController::class, 'togglePin', [(string) $this->conv, (string) $msgId]);

        $this->assertSame('1', $result->echoed);
        $this->assertNotNull($this->pdo->query("SELECT pinned_at FROM teams_messages WHERE id = {$msgId}")->fetchColumn());
    }

    public function testPinnedListReturnsPinnedMessages(): void
    {
        $msgId = $this->insertMessage(2, 'importante');
        $this->pdo->exec("UPDATE teams_messages SET pinned_at = '2024-01-01 00:00:00', pinned_by = 1 WHERE id = {$msgId}");

        $result = $this->dispatch(MessageController::class, 'pinnedList', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['pinnedCount']);
    }

    public function testReadersReturnsReaders(): void
    {
        $msgId = $this->insertMessage(1, 'letto?');
        $this->pdo->exec("UPDATE teams_conversation_members SET last_read_at = '2099-01-01 00:00:00' WHERE conversation_id = {$this->conv} AND user_id = 2");

        $result = $this->dispatch(MessageController::class, 'readers', [(string) $this->conv, (string) $msgId]);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['readers']);
    }

    public function testDestroySoftDeletesAndRendersBubble(): void
    {
        $msgId = $this->insertMessage(1, 'da eliminare');

        $result = $this->dispatch(MessageController::class, 'destroy', [(string) $this->conv, (string) $msgId]);

        $this->assertTrue($result->didRender());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM teams_messages WHERE id = {$msgId}")->fetchColumn());
    }

    public function testAttachmentReturns404WhenNotFound(): void
    {
        $this->dispatch(MessageController::class, 'attachment', ['999']);

        $this->assertSame(404, http_response_code());
    }
}
