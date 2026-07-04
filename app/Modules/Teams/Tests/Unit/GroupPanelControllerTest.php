<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Controllers\GroupPanelController;
use Tests\ControllerTestCase;

class GroupPanelControllerTest extends ControllerTestCase
{
    private int $conv;

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
                type        TEXT NOT NULL DEFAULT "group",
                name        TEXT NULL,
                description TEXT NULL,
                avatar_path TEXT NULL,
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
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                user_id         INTEGER NULL,
                body            TEXT NOT NULL,
                type            TEXT NOT NULL DEFAULT "text",
                deleted_at      TEXT NULL,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP
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

        $this->conv = $this->insertRow('teams_conversations', ['type' => 'group', 'name' => 'Team Alpha', 'created_by' => 1]);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 1, 'role' => 'admin']);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 2, 'role' => 'member']);

        $this->actingAs(1, ['teams.view']);
    }

    public function testHeaderRendersGroupInfo(): void
    {
        $result = $this->dispatch(GroupPanelController::class, 'header', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertSame('Team Alpha', $result->renderedData()['info']['name']);
    }

    public function testMediaReturnsMediaPage(): void
    {
        $msg = $this->insertRow('teams_messages', ['conversation_id' => $this->conv, 'user_id' => 2, 'body' => '[img]']);
        $this->insertRow('teams_message_attachments', [
            'message_id' => $msg, 'original_name' => 'foto.png', 'stored_name' => 'x.png',
            'mime_type' => 'image/png', 'extension' => 'png',
        ]);

        $result = $this->dispatch(GroupPanelController::class, 'media', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testFilesReturnsFilesPage(): void
    {
        $msg = $this->insertRow('teams_messages', ['conversation_id' => $this->conv, 'user_id' => 2, 'body' => '[pdf]']);
        $this->insertRow('teams_message_attachments', [
            'message_id' => $msg, 'original_name' => 'doc.pdf', 'stored_name' => 'x.pdf',
            'mime_type' => 'application/pdf', 'extension' => 'pdf',
        ]);

        $result = $this->dispatch(GroupPanelController::class, 'files', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testLinksReturnsLinksPage(): void
    {
        $this->insertRow('teams_messages', ['conversation_id' => $this->conv, 'user_id' => 2, 'body' => 'vedi https://example.com']);

        $result = $this->dispatch(GroupPanelController::class, 'links', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testHeaderReturns403ForOutsider(): void
    {
        $this->insertRow('users', ['name' => 'Carlo']);
        $this->actingAs(3, ['teams.view']);

        $this->dispatch(GroupPanelController::class, 'header', [(string) $this->conv]);

        $this->assertSame(403, http_response_code());
    }
}
