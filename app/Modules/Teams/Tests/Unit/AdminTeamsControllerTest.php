<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Controllers\AdminTeamsController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AdminTeamsController.
 *
 * index(), conversationTable(), archiveConversation() and destroy() all end up
 * calling ConversationRepository::adminList()/adminCount(), which use
 * `GROUP_CONCAT(... SEPARATOR ...)` — MySQL-only syntax with no SQLite
 * equivalent (SQLite's group_concat() has a different, positional-argument
 * form and no ORDER BY support inside the aggregate). Those four actions are
 * therefore only exercised via manual QA against real MariaDB (Gate 3).
 * cleanupPreview()/triggerCleanup() hit only MessageRepository::countOlderThan()/
 * cleanupOldMessages(), portable after the DATE_SUB() -> monthsAgo() fix, and are
 * covered here.
 */
class AdminTeamsControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
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
        ');

        $this->insertRow('users', ['name' => 'Admin']);
        $this->actingAs(1, ['teams.admin']);
        $_SESSION['user_name'] = 'Admin';
    }

    public function testCleanupPreviewEchoesCountOfOldMessages(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-8 months'));
        $recent = date('Y-m-d H:i:s', strtotime('-1 month'));
        $this->insertRow('teams_messages', ['conversation_id' => 1, 'user_id' => 1, 'body' => 'old', 'created_at' => $old]);
        $this->insertRow('teams_messages', ['conversation_id' => 1, 'user_id' => 1, 'body' => 'recent', 'created_at' => $recent]);

        $result = $this->withGet(['months' => '6'])
            ->dispatch(AdminTeamsController::class, 'cleanupPreview', []);

        $this->assertStringContainsString('>1<', $result->echoed);
    }

    public function testTriggerCleanupSoftDeletesOldMessagesAndRedirects(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-8 months'));
        $recent = date('Y-m-d H:i:s', strtotime('-1 month'));
        $oldId = $this->insertRow('teams_messages', ['conversation_id' => 1, 'user_id' => 1, 'body' => 'old', 'created_at' => $old]);
        $recentId = $this->insertRow('teams_messages', ['conversation_id' => 1, 'user_id' => 1, 'body' => 'recent', 'created_at' => $recent]);

        $result = $this->withPost(['months' => '6'])
            ->dispatch(AdminTeamsController::class, 'triggerCleanup', []);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM teams_messages WHERE id = {$oldId}")->fetchColumn());
        $this->assertNull($this->pdo->query("SELECT deleted_at FROM teams_messages WHERE id = {$recentId}")->fetchColumn());
    }
}
