<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Controllers\TeamsController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for TeamsController's main routes.
 *
 * MessageRepository::searchForUser()/searchInConversationForUser() fall back to
 * plain LIKE only when every query token is < 3 chars (see
 * MessageRepository::toBooleanModeQuery()); longer queries switch to
 * MySQL-only `MATCH ... AGAINST`, which SQLite cannot even parse. Search
 * tests here deliberately use short queries to stay on the LIKE path.
 */
class TeamsControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                name         TEXT NOT NULL,
                email        TEXT NULL,
                username     TEXT NULL,
                avatar_path  TEXT NULL,
                is_active    INTEGER NOT NULL DEFAULT 1,
                deleted_at   TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                type        TEXT NOT NULL DEFAULT "direct",
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
            CREATE TABLE IF NOT EXISTS teams_typing (
                user_id         INTEGER NOT NULL,
                conversation_id INTEGER NOT NULL,
                started_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, conversation_id)
            );
            CREATE TABLE IF NOT EXISTS notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                title      TEXT NOT NULL,
                body       TEXT NULL,
                link       TEXT NULL,
                read_at    TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->insertRow('users', ['name' => 'Alice']);
        $this->insertRow('users', ['name' => 'Bob']);
        $this->insertRow('users', ['name' => 'Carlo']);

        $this->actingAs(1, ['teams.view', 'teams.create', 'teams.delete']);
        $_SESSION['user_name'] = 'Alice';
    }

    private function createDirect(int $otherUserId = 2): int
    {
        $id = $this->insertRow('teams_conversations', ['type' => 'direct', 'created_by' => 1]);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $id, 'user_id' => 1, 'role' => 'admin']);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $id, 'user_id' => $otherUserId, 'role' => 'member']);
        return $id;
    }

    private function createGroup(): int
    {
        $id = $this->insertRow('teams_conversations', ['type' => 'group', 'name' => 'Team Alpha', 'created_by' => 1]);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $id, 'user_id' => 1, 'role' => 'admin']);
        $this->insertRow('teams_conversation_members', ['conversation_id' => $id, 'user_id' => 2, 'role' => 'member']);
        return $id;
    }

    // ── index / show ─────────────────────────────────────────────────────────

    public function testIndexRendersConversationList(): void
    {
        $this->createDirect();

        $result = $this->withGet([])->dispatch(TeamsController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Teams/Views/index', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['conversations']);
    }

    public function testShowRedirectsWhenConversationNotFound(): void
    {
        $result = $this->dispatch(TeamsController::class, 'show', ['999']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/teams.index', $result->redirectUrl());
    }

    public function testShowRendersConversationForMember(): void
    {
        $convId = $this->createDirect();
        $this->insertRow('teams_messages', ['conversation_id' => $convId, 'user_id' => 2, 'body' => 'Ciao!']);

        $result = $this->dispatch(TeamsController::class, 'show', [(string) $convId]);

        $this->assertTrue($result->didRender());
        $this->assertSame('Teams/Views/index', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['messages']);
    }

    // ── store / storeDirect ──────────────────────────────────────────────────

    public function testStoreCreatesGroupAndRedirects(): void
    {
        $result = $this->withPost(['name' => 'Nuovo Gruppo', 'description' => '', 'members' => ['2', '3']])
            ->dispatch(TeamsController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertStringStartsWith('/teams.show', $result->redirectUrl());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_conversations WHERE type = 'group' AND name = 'Nuovo Gruppo'")->fetchColumn()
        );
    }

    public function testStoreRejectsMissingName(): void
    {
        $result = $this->withPost(['name' => '', 'members' => ['2']])
            ->dispatch(TeamsController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/teams.index', $result->redirectUrl());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM teams_conversations')->fetchColumn());
    }

    public function testStoreDirectCreatesConversationAndRedirects(): void
    {
        $result = $this->withPost(['user_id' => '2'])
            ->dispatch(TeamsController::class, 'storeDirect');

        $this->assertTrue($result->isRedirect());
        $this->assertStringStartsWith('/teams.show', $result->redirectUrl());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM teams_conversations WHERE type = 'direct'")->fetchColumn()
        );
    }

    // ── update / archive / leave ─────────────────────────────────────────────

    public function testUpdateRenamesGroupAndRedirects(): void
    {
        $convId = $this->createGroup();

        $result = $this->withPost(['name' => 'Nuovo Nome', 'description' => 'desc'])
            ->dispatch(TeamsController::class, 'update', [(string) $convId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Nuovo Nome', $this->pdo->query("SELECT name FROM teams_conversations WHERE id = {$convId}")->fetchColumn());
    }

    public function testArchiveArchivesConversationAndRedirects(): void
    {
        $convId = $this->createGroup();

        $result = $this->dispatch(TeamsController::class, 'archive', [(string) $convId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/teams.index', $result->redirectUrl());
        $this->assertNotNull($this->pdo->query("SELECT archived_at FROM teams_conversations WHERE id = {$convId}")->fetchColumn());
    }

    public function testLeaveRemovesMemberAndRedirects(): void
    {
        $convId = $this->createGroup();

        $result = $this->dispatch(TeamsController::class, 'leave', [(string) $convId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/teams.index', $result->redirectUrl());
        $this->assertNotNull(
            $this->pdo->query("SELECT left_at FROM teams_conversation_members WHERE conversation_id = {$convId} AND user_id = 1")->fetchColumn()
        );
    }

    // ── toggleMute / hide / unhide ───────────────────────────────────────────

    public function testToggleMuteTogglesAndRendersPartial(): void
    {
        $convId = $this->createDirect();

        $result = $this->asHtmx()->dispatch(TeamsController::class, 'toggleMute', [(string) $convId]);

        $this->assertTrue($result->didRender());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT notifications_muted FROM teams_conversation_members WHERE conversation_id = {$convId} AND user_id = 1")->fetchColumn()
        );
    }

    public function testHideHidesConversationAndRedirects(): void
    {
        $convId = $this->createDirect();

        $result = $this->dispatch(TeamsController::class, 'hide', [(string) $convId]);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull(
            $this->pdo->query("SELECT hidden_at FROM teams_conversation_members WHERE conversation_id = {$convId} AND user_id = 1")->fetchColumn()
        );
    }

    public function testUnhideReturns204(): void
    {
        $convId = $this->createDirect();
        $this->pdo->exec("UPDATE teams_conversation_members SET hidden_at = '2024-01-01 00:00:00' WHERE conversation_id = {$convId} AND user_id = 1");

        $this->asHtmx()->dispatch(TeamsController::class, 'unhide', [(string) $convId]);

        $this->assertSame(204, http_response_code());
        $this->assertNull(
            $this->pdo->query("SELECT hidden_at FROM teams_conversation_members WHERE conversation_id = {$convId} AND user_id = 1")->fetchColumn()
        );
    }

    // ── search / unreadCount / presence ──────────────────────────────────────

    public function testSearchWithShortQueryUsesLikeFallback(): void
    {
        $convId = $this->createDirect();
        $this->insertRow('teams_messages', ['conversation_id' => $convId, 'user_id' => 2, 'body' => 'ciao a tutti']);

        // Single 2-char token keeps MessageRepository on the LIKE fallback path.
        $result = $this->withGet(['q' => 'ao'])->dispatch(TeamsController::class, 'search');

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['searchResults']);
    }

    public function testUnreadCountRendersBadge(): void
    {
        $result = $this->dispatch(TeamsController::class, 'unreadCount');

        $this->assertTrue($result->didRender());
        $this->assertSame('Teams/Views/partials/unread_badge', $result->renderedTemplate());
        $this->assertSame(0, $result->renderedData()['count']);
    }

    public function testHeartbeatReturns204(): void
    {
        $this->dispatch(TeamsController::class, 'heartbeat');

        $this->assertSame(204, http_response_code());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM teams_user_presence WHERE user_id = 1')->fetchColumn());
    }

    public function testTypingReturns204(): void
    {
        $convId = $this->createDirect();

        $this->withPost(['conversation_id' => (string) $convId])
            ->dispatch(TeamsController::class, 'typing');

        $this->assertSame(204, http_response_code());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM teams_typing WHERE user_id = 1')->fetchColumn());
    }

    public function testUserSearchRendersResults(): void
    {
        $result = $this->withGet(['q' => 'Bob'])->dispatch(TeamsController::class, 'userSearch');

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['users']);
    }

    public function testMentionAutocompleteReturnsJson(): void
    {
        $convId = $this->createGroup();

        $result = $this->dispatch(TeamsController::class, 'mentionAutocomplete', [(string) $convId]);

        $decoded = json_decode($result->echoed, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Bob', $decoded[0]['name']);
    }
}
