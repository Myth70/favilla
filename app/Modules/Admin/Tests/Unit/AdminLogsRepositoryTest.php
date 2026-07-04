<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\AdminLogsRepository;
use Tests\ModuleTestCase;

class AdminLogsRepositoryTest extends ModuleTestCase
{
    private AdminLogsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Users table with username column (needed by JOIN in audit/session queries)
        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                username    TEXT    DEFAULT NULL,
                avatar_path TEXT    DEFAULT NULL
            )
        ');

        // Funzioni MySQL mancanti in SQLite
        $this->pdo->sqliteCreateFunction('CURDATE', fn () => date('Y-m-d'), 0);
        $this->pdo->sqliteCreateFunction('DATE', fn ($val) => substr($val, 0, 10), 1);
        $this->pdo->sqliteCreateFunction('IF', fn ($cond, $t, $f) => $cond ? $t : $f, 3);

        $this->migrate('
            CREATE TABLE audit_logs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER,
                action      TEXT    NOT NULL,
                entity      TEXT,
                entity_id   INTEGER,
                old_value   TEXT,
                new_value   TEXT,
                ip          TEXT,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->migrate('
            CREATE TABLE login_attempts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                email       TEXT    NOT NULL,
                ip_address  TEXT    NOT NULL,
                success     INTEGER DEFAULT 0,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->migrate("
            CREATE TABLE sessions (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL,
                token_hash    TEXT    NOT NULL DEFAULT '',
                ip            TEXT,
                user_agent    TEXT,
                last_activity TEXT    DEFAULT CURRENT_TIMESTAMP,
                expires_at    TEXT    NOT NULL
            )
        ");

        $this->migrate('
            CREATE TABLE password_resets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL,
                token_hash  TEXT    NOT NULL,
                expires_at  TEXT    NOT NULL,
                used_at     TEXT,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->repo = new AdminLogsRepository();
    }

    // ── AUDIT LOGS ───────────────────────────────────────────────

    public function testListAuditReturnsEmpty(): void
    {
        $result = $this->repo->listAudit();
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['items']);
    }

    public function testListAuditReturnsLogs(): void
    {
        $this->insertRow('audit_logs', [
            'action' => 'user_created', 'entity' => 'user', 'entity_id' => 1, 'ip' => '127.0.0.1',
        ]);
        $this->insertRow('audit_logs', [
            'action' => 'user_deleted', 'entity' => 'user', 'entity_id' => 2, 'ip' => '127.0.0.1',
        ]);

        $result = $this->repo->listAudit();

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    public function testListAuditFiltersAction(): void
    {
        $this->insertRow('audit_logs', ['action' => 'user_created', 'ip' => '127.0.0.1']);
        $this->insertRow('audit_logs', ['action' => 'user_deleted', 'ip' => '127.0.0.1']);

        $result = $this->repo->listAudit(['action' => 'user_created']);

        $this->assertSame(1, $result['total']);
        $this->assertSame('user_created', $result['items'][0]['action']);
    }

    public function testListAuditFiltersIp(): void
    {
        $this->insertRow('audit_logs', ['action' => 'test', 'ip' => '192.168.1.1']);
        $this->insertRow('audit_logs', ['action' => 'test', 'ip' => '10.0.0.1']);

        $result = $this->repo->listAudit(['ip' => '192.168']);

        $this->assertSame(1, $result['total']);
    }

    public function testListAuditPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->insertRow('audit_logs', ['action' => 'test', 'ip' => '127.0.0.1']);
        }

        $result = $this->repo->listAudit([], 1, 2);

        $this->assertSame(5, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertSame(3, $result['lastPage']);
    }

    public function testGetAuditStats(): void
    {
        $this->insertRow('audit_logs', ['action' => 'user_created', 'ip' => '127.0.0.1']);
        $this->insertRow('audit_logs', ['action' => 'user_created', 'ip' => '127.0.0.1']);
        $this->insertRow('audit_logs', ['action' => 'user_deleted', 'ip' => '127.0.0.1']);

        $stats = $this->repo->getAuditStats();

        $this->assertSame(3, $stats['total']);
        $this->assertArrayHasKey('today', $stats);
        $this->assertArrayHasKey('actions', $stats);
    }

    public function testGetDistinctAuditActions(): void
    {
        $this->insertRow('audit_logs', ['action' => 'user_created', 'ip' => '127.0.0.1']);
        $this->insertRow('audit_logs', ['action' => 'user_deleted', 'ip' => '127.0.0.1']);
        $this->insertRow('audit_logs', ['action' => 'user_created', 'ip' => '127.0.0.1']);

        $actions = $this->repo->getDistinctAuditActions();

        $this->assertCount(2, $actions);
        $this->assertContains('user_created', $actions);
        $this->assertContains('user_deleted', $actions);
    }

    // ── LOGIN ATTEMPTS ───────────────────────────────────────────

    public function testListAttemptsReturnsEmpty(): void
    {
        $result = $this->repo->listAttempts();
        $this->assertSame(0, $result['total']);
    }

    public function testListAttemptsFiltersEmail(): void
    {
        $this->insertRow('login_attempts', ['email' => 'admin@test.com', 'ip_address' => '127.0.0.1', 'success' => 0]);
        $this->insertRow('login_attempts', ['email' => 'user@test.com', 'ip_address' => '127.0.0.1', 'success' => 1]);

        $result = $this->repo->listAttempts(['email' => 'admin']);

        $this->assertSame(1, $result['total']);
    }

    public function testListAttemptsFiltersSuccess(): void
    {
        $this->insertRow('login_attempts', ['email' => 'a@test.com', 'ip_address' => '127.0.0.1', 'success' => 0]);
        $this->insertRow('login_attempts', ['email' => 'b@test.com', 'ip_address' => '127.0.0.1', 'success' => 1]);

        $result = $this->repo->listAttempts(['success' => '0']);
        $this->assertSame(1, $result['total']);

        $result = $this->repo->listAttempts(['success' => '1']);
        $this->assertSame(1, $result['total']);
    }

    public function testGetAttemptsStats(): void
    {
        $this->insertRow('login_attempts', ['email' => 'a@test.com', 'ip_address' => '127.0.0.1', 'success' => 0]);
        $this->insertRow('login_attempts', ['email' => 'b@test.com', 'ip_address' => '127.0.0.1', 'success' => 1]);

        $stats = $this->repo->getAttemptsStats();

        $this->assertSame(2, $stats['total']);
        $this->assertArrayHasKey('todayFailed', $stats);
        $this->assertArrayHasKey('todaySuccess', $stats);
    }

    // ── SESSIONS ─────────────────────────────────────────────────

    public function testListSessionsReturnsEmpty(): void
    {
        $result = $this->repo->listSessions();
        $this->assertSame(0, $result['total']);
    }

    public function testListSessionsReturnsSessions(): void
    {
        $userId = $this->insertRow('users', ['name' => 'TestUser', 'username' => 'testuser']);
        $this->insertRow('sessions', [
            'user_id' => $userId, 'ip' => '127.0.0.1',
            'user_agent' => 'Mozilla', 'expires_at' => '2099-12-31 23:59:59',
        ]);

        $result = $this->repo->listSessions();

        $this->assertSame(1, $result['total']);
        $this->assertSame('TestUser', $result['items'][0]['user_name']);
    }

    public function testGetSessionsStats(): void
    {
        $userId = $this->insertRow('users', ['name' => 'TestUser', 'username' => 'testuser']);
        $this->insertRow('sessions', [
            'user_id' => $userId, 'ip' => '127.0.0.1',
            'expires_at' => '2099-12-31 23:59:59',
        ]);
        $this->insertRow('sessions', [
            'user_id' => $userId, 'ip' => '127.0.0.1',
            'expires_at' => '2020-01-01 00:00:00',
        ]);

        $stats = $this->repo->getSessionsStats();

        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('expired', $stats);
    }

    // ── EXPORT ───────────────────────────────────────────────────

    public function testExportAuditReturnsRows(): void
    {
        $this->insertRow('audit_logs', ['action' => 'test', 'ip' => '127.0.0.1']);

        $rows = $this->repo->exportAudit();

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('action', $rows[0]);
    }

    public function testExportAttemptsReturnsRows(): void
    {
        $this->insertRow('login_attempts', ['email' => 'a@test.com', 'ip_address' => '127.0.0.1', 'success' => 1]);

        $rows = $this->repo->exportAttempts();

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('email', $rows[0]);
    }

    public function testExportSessionsReturnsRows(): void
    {
        $userId = $this->insertRow('users', ['name' => 'TestUser', 'username' => 'testuser']);
        $this->insertRow('sessions', [
            'user_id' => $userId, 'ip' => '127.0.0.1',
            'expires_at' => '2099-12-31 23:59:59',
        ]);

        $rows = $this->repo->exportSessions();

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('utente', $rows[0]);
    }

    // ── EXPORT_LIMIT constant ────────────────────────────────────

    public function testExportLimitConstantExists(): void
    {
        $this->assertSame(10000, AdminLogsRepository::EXPORT_LIMIT);
    }
}
