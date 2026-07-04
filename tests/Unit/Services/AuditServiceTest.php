<?php

namespace Tests\Unit\Services;

use App\Services\AuditService;
use Tests\ModuleTestCase;

class AuditServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate("
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                action TEXT NOT NULL,
                entity TEXT NOT NULL,
                entity_id INTEGER DEFAULT NULL,
                old_value TEXT DEFAULT NULL,
                new_value TEXT DEFAULT NULL,
                ip TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");
    }

    public function test_log_inserts_row_with_expected_columns(): void
    {
        $_SESSION['user_id'] = 5;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        AuditService::log('item_created', 'item', 42, null, ['name' => 'foo']);

        $row = $this->pdo->query('SELECT * FROM audit_logs')->fetch();
        $this->assertSame('item_created', $row['action']);
        $this->assertSame('item', $row['entity']);
        $this->assertSame(42, (int) $row['entity_id']);
        $this->assertSame(5, (int) $row['user_id']);
        $this->assertSame('10.0.0.5', $row['ip']);
        $this->assertNull($row['old_value']);
        $this->assertSame('{"name":"foo"}', $row['new_value']);
    }

    public function test_log_encodes_utf8_without_escaping(): void
    {
        AuditService::log('edit', 'item', 1, ['name' => 'vecchio'], ['name' => 'àèì']);

        $row = $this->pdo->query('SELECT old_value, new_value FROM audit_logs')->fetch();
        $this->assertSame('{"name":"vecchio"}', $row['old_value']);
        $this->assertStringContainsString('àèì', $row['new_value']);
    }

    public function test_log_uses_explicit_user_and_ip_overrides(): void
    {
        $_SESSION['user_id'] = 1;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        AuditService::log('x', 'y', 1, null, null, 99, '203.0.113.9');

        $row = $this->pdo->query('SELECT user_id, ip FROM audit_logs')->fetch();
        $this->assertSame(99, (int) $row['user_id']);
        $this->assertSame('203.0.113.9', $row['ip']);
    }

    public function test_log_handles_null_entity_id(): void
    {
        AuditService::log('backup_run', 'system', null);
        $row = $this->pdo->query('SELECT entity_id, action FROM audit_logs')->fetch();
        $this->assertNull($row['entity_id']);
        $this->assertSame('backup_run', $row['action']);
    }

    public function test_log_swallows_failures_silently(): void
    {
        $this->pdo->exec('DROP TABLE audit_logs');

        // Should not throw despite missing table
        AuditService::log('x', 'y', 1);
        $this->assertTrue(true);
    }
}
