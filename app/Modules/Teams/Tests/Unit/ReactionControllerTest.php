<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Controllers\ReactionController;
use Tests\ControllerTestCase;

class ReactionControllerTest extends ControllerTestCase
{
    private int $conv;
    private int $msg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                avatar_path TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                type        TEXT NOT NULL DEFAULT "direct",
                created_by  INTEGER NULL,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
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
            CREATE TABLE IF NOT EXISTS teams_message_reactions (
                message_id INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                emoji      TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id, user_id, emoji)
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
        ');

        $this->insertRow('users', ['name' => 'Alice']);
        $this->insertRow('users', ['name' => 'Bob']);

        $this->conv = $this->insertRow('teams_conversations', ['type' => 'direct', 'created_by' => 1]);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 1, 'role' => 'admin']);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 2, 'role' => 'member']);
        $this->msg = $this->insertRow('teams_messages', ['conversation_id' => $this->conv, 'user_id' => 2, 'body' => 'ciao']);

        $this->actingAs(1, ['teams.view', 'teams.create']);
    }

    public function testToggleAddsReactionAndRendersBar(): void
    {
        $result = $this->withPost(['emoji' => '👍'])
            ->dispatch(ReactionController::class, 'toggle', [(string) $this->conv, (string) $this->msg]);

        $this->assertTrue($result->didRender());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_message_reactions WHERE message_id = {$this->msg} AND user_id = 1")->fetchColumn()
        );
    }

    public function testToggleRemovesReactionOnSecondCall(): void
    {
        $this->withPost(['emoji' => '👍'])
            ->dispatch(ReactionController::class, 'toggle', [(string) $this->conv, (string) $this->msg]);

        $this->withPost(['emoji' => '👍'])
            ->dispatch(ReactionController::class, 'toggle', [(string) $this->conv, (string) $this->msg]);

        $this->assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_message_reactions WHERE message_id = {$this->msg} AND user_id = 1")->fetchColumn()
        );
    }

    public function testToggleRejectsDisallowedEmoji(): void
    {
        $this->withPost(['emoji' => '🤖🤖🤖'])
            ->dispatch(ReactionController::class, 'toggle', [(string) $this->conv, (string) $this->msg]);

        $this->assertSame(422, http_response_code());
    }
}
