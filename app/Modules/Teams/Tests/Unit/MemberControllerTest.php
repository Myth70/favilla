<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Controllers\MemberController;
use Tests\ControllerTestCase;

class MemberControllerTest extends ControllerTestCase
{
    private int $conv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                email      TEXT NULL,
                avatar_path TEXT NULL,
                is_active  INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                type        TEXT NOT NULL DEFAULT "group",
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
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                user_id         INTEGER NULL,
                body            TEXT NOT NULL,
                type            TEXT NOT NULL DEFAULT "text",
                edited_at       TEXT NULL,
                deleted_at      TEXT NULL,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->insertRow('users', ['name' => 'Alice']);
        $this->insertRow('users', ['name' => 'Bob']);
        $this->insertRow('users', ['name' => 'Carlo']);

        $this->conv = $this->insertRow('teams_conversations', ['type' => 'group', 'name' => 'Team Alpha', 'created_by' => 1]);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 1, 'role' => 'admin']);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $this->conv, 'user_id' => 2, 'role' => 'member']);

        $this->actingAs(1, ['teams.view', 'teams.create', 'teams.delete']);
        $_SESSION['user_name'] = 'Alice';
    }

    public function testIndexRendersMemberList(): void
    {
        $result = $this->dispatch(MemberController::class, 'index', [(string) $this->conv]);

        $this->assertTrue($result->didRender());
        $this->assertCount(2, $result->renderedData()['members']);
    }

    public function testStoreAddsMembersAndRedirects(): void
    {
        $result = $this->withPost(['members' => ['3']])
            ->dispatch(MemberController::class, 'store', [(string) $this->conv]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_conversation_members WHERE conversation_id = {$this->conv} AND user_id = 3")->fetchColumn()
        );
    }

    public function testStoreRejectsEmptySelection(): void
    {
        $result = $this->withPost(['members' => []])
            ->dispatch(MemberController::class, 'store', [(string) $this->conv]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(
            2,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_conversation_members WHERE conversation_id = {$this->conv}")->fetchColumn()
        );
    }

    public function testDestroyRemovesMemberAndRendersPartial(): void
    {
        $result = $this->asHtmx()->dispatch(MemberController::class, 'destroy', [(string) $this->conv, '2']);

        $this->assertTrue($result->didRender());
        $this->assertNotNull(
            $this->pdo->query("SELECT left_at FROM teams_conversation_members WHERE conversation_id = {$this->conv} AND user_id = 2")->fetchColumn()
        );
    }
}
