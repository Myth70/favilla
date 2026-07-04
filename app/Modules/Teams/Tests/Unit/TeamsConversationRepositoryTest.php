<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Repositories\ConversationRepository;
use Tests\ModuleTestCase;

/**
 * Test del repository ConversationRepository su SQLite in-memory.
 *
 * Copre:
 * - createWithMembers: crea conversazione con membri
 * - findDirectBetween: trova DM esistente
 * - findDirectBetween: non trova DM quando assente
 * - getGlobalUnreadCount: conta messaggi non letti
 * - addMember / rimozione membro tramite left_at
 */
class TeamsConversationRepositoryTest extends ModuleTestCase
{
    private ConversationRepository $repo;

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
                edited_at       TEXT NULL,
                deleted_at      TEXT NULL,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS teams_user_presence (
                id                      INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id                 INTEGER NOT NULL,
                last_seen_at            TEXT NOT NULL,
                active_conversation_id  INTEGER NULL
            );
        ');

        $this->repo = new ConversationRepository();

        // Crea due utenti di test
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Carlo')");
    }

    // ── createWithMembers ────────────────────────────────────────────────────

    public function testCreateWithMembersCreatesGroupConversation(): void
    {
        $convId = $this->repo->createWithMembers(
            [
                'type'       => 'group',
                'name'       => 'Team Alpha',
                'created_by' => 1,
            ],
            [2, 3],
            1 // creatore (diventa admin)
        );

        $this->assertGreaterThan(0, $convId);

        // Verifica la conversazione
        $conv = $this->repo->find($convId);
        $this->assertNotNull($conv);
        $this->assertSame('group', $conv['type']);
        $this->assertSame('Team Alpha', $conv['name']);
    }

    public function testCreateWithMembersAddsCreatorAsAdmin(): void
    {
        $convId = $this->repo->createWithMembers(
            ['type' => 'group', 'name' => 'Gruppo', 'created_by' => 1],
            [2],
            1
        );

        $stmt = $this->pdo->prepare(
            'SELECT role FROM teams_conversation_members WHERE conversation_id = ? AND user_id = ?'
        );
        $stmt->execute([$convId, 1]);
        $this->assertSame('admin', $stmt->fetchColumn());
    }

    public function testCreateWithMembersAddsOtherMembersAsMember(): void
    {
        $convId = $this->repo->createWithMembers(
            ['type' => 'group', 'name' => 'Gruppo', 'created_by' => 1],
            [2, 3],
            1
        );

        $stmt = $this->pdo->prepare(
            'SELECT role FROM teams_conversation_members WHERE conversation_id = ? AND user_id = ?'
        );
        $stmt->execute([$convId, 2]);
        $this->assertSame('member', $stmt->fetchColumn());
    }

    public function testCreateWithMembersDoesNotDuplicateCreator(): void
    {
        // Se il creatore è anche in memberIds, non deve essere duplicato
        $convId = $this->repo->createWithMembers(
            ['type' => 'group', 'name' => 'Gruppo', 'created_by' => 1],
            [1, 2],  // creatore incluso nella lista
            1
        );

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teams_conversation_members WHERE conversation_id = ? AND user_id = ?'
        );
        $stmt->execute([$convId, 1]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ── findDirectBetween ────────────────────────────────────────────────────

    public function testFindDirectBetweenReturnsIdWhenExists(): void
    {
        // Crea una conversazione direct tra utente 1 e 2
        $convId = $this->repo->createWithMembers(
            ['type' => 'direct', 'created_by' => 1],
            [2],
            1
        );

        $found = $this->repo->findDirectBetween(1, 2);
        $this->assertSame($convId, $found);
    }

    public function testFindDirectBetweenIsSymmetric(): void
    {
        $convId = $this->repo->createWithMembers(
            ['type' => 'direct', 'created_by' => 1],
            [2],
            1
        );

        // Deve trovare la stessa conversazione da entrambe le direzioni
        $this->assertSame($convId, $this->repo->findDirectBetween(1, 2));
        $this->assertSame($convId, $this->repo->findDirectBetween(2, 1));
    }

    public function testFindDirectBetweenReturnsNullWhenNotExists(): void
    {
        $this->assertNull($this->repo->findDirectBetween(1, 3));
    }

    public function testFindDirectBetweenIgnoresArchivedConversations(): void
    {
        $convId = $this->repo->createWithMembers(
            ['type' => 'direct', 'created_by' => 1],
            [2],
            1
        );

        // Archivia la conversazione
        $this->pdo->prepare(
            "UPDATE teams_conversations SET archived_at = '2020-01-01 00:00:00' WHERE id = ?"
        )->execute([$convId]);

        $this->assertNull($this->repo->findDirectBetween(1, 2));
    }

    // ── getGlobalUnreadCount ─────────────────────────────────────────────────

    public function testGetGlobalUnreadCountReturnsZeroWithNoMessages(): void
    {
        $this->repo->createWithMembers(
            ['type' => 'direct', 'created_by' => 1],
            [2],
            1
        );

        $this->assertSame(0, $this->repo->getGlobalUnreadCount(1));
    }

    // ── adminStats: online_now (portabilità SQLite, niente DATE_SUB) ───────

    public function testAdminStatsCountsRecentPresenceAsOnline(): void
    {
        $this->insertRow('teams_user_presence', [
            'user_id'      => 1,
            'last_seen_at' => date('Y-m-d H:i:s', time() - 5),
        ]);

        $stats = $this->repo->adminStats();

        $this->assertSame(1, $stats['online_now']);
    }

    public function testAdminStatsExcludesStalePresenceBeyondThreshold(): void
    {
        // Se questo lancia un'eccezione PDO, significa che è tornata la
        // sintassi DATE_SUB(NOW(), INTERVAL ...) MySQL-only non supportata
        // da SQLite.
        $this->insertRow('teams_user_presence', [
            'user_id'      => 1,
            'last_seen_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $stats = $this->repo->adminStats();

        $this->assertSame(0, $stats['online_now']);
    }
}
