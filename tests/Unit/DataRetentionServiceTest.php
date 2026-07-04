<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DataRetentionService;
use Tests\ModuleTestCase;

class DataRetentionServiceTest extends ModuleTestCase
{
    private DataRetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE data_retention_policies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                table_name TEXT NOT NULL,
                date_column TEXT NOT NULL,
                retention_days INTEGER NOT NULL DEFAULT 90,
                action TEXT NOT NULL DEFAULT "delete",
                anonymize_fields TEXT DEFAULT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                last_run_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            );
        ');

        $this->service = new DataRetentionService();
    }

    public function testExecuteAllPurgesExpiredRowsForZeroDayPolicies(): void
    {
        $this->migrate('
            CREATE TABLE sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                expires_at TEXT NOT NULL
            );
        ');

        $this->insertRow('data_retention_policies', [
            'entity' => 'Sessioni Scadute',
            'table_name' => 'sessions',
            'date_column' => 'expires_at',
            'retention_days' => 0,
            'action' => 'delete',
            'enabled' => 1,
        ]);

        $expiredId = $this->insertRow('sessions', [
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);
        $futureId = $this->insertRow('sessions', [
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $results = $this->service->executeAll(false);

        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['affected']);
        $this->assertSame('delete', $results[0]['action']);

        $expiredCount = (int) $this->pdo->query('SELECT COUNT(*) FROM sessions WHERE id = ' . $expiredId)->fetchColumn();
        $futureCount = (int) $this->pdo->query('SELECT COUNT(*) FROM sessions WHERE id = ' . $futureId)->fetchColumn();
        $lastRunAt = $this->pdo->query('SELECT last_run_at FROM data_retention_policies WHERE entity = "Sessioni Scadute"')->fetchColumn();

        $this->assertSame(0, $expiredCount);
        $this->assertSame(1, $futureCount);
        $this->assertNotEmpty($lastRunAt);
    }

    public function testAnonymizePolicyWithoutValidColumnsFailsWithoutDeletingData(): void
    {
        $this->migrate('
            CREATE TABLE customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
        ');

        $this->insertRow('data_retention_policies', [
            'entity' => 'Clienti Legacy',
            'table_name' => 'customers',
            'date_column' => 'created_at',
            'retention_days' => 1,
            'action' => 'anonymize',
            'anonymize_fields' => '["missing_column"]',
            'enabled' => 1,
        ]);

        $customerId = $this->insertRow('customers', [
            'email' => 'cliente@example.test',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
        ]);

        $results = $this->service->executeAll(false);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('error', $results[0]);
        $this->assertStringContainsString('non contiene colonne esistenti', $results[0]['error']);

        $row = $this->pdo->query('SELECT email FROM customers WHERE id = ' . $customerId)->fetch();
        $lastRunAt = $this->pdo->query('SELECT last_run_at FROM data_retention_policies WHERE entity = "Clienti Legacy"')->fetchColumn();

        $this->assertSame('cliente@example.test', $row['email']);
        $this->assertNull($lastRunAt);
    }

    public function testDeletePolicyOnSoftDeleteTablePurgesOnlyDeletedRows(): void
    {
        $this->migrate('
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                created_at TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            );
        ');

        $this->insertRow('data_retention_policies', [
            'entity' => 'Notifiche',
            'table_name' => 'notifications',
            'date_column' => 'created_at',
            'retention_days' => 1,
            'action' => 'delete',
            'enabled' => 1,
        ]);

        $activeId = $this->insertRow('notifications', [
            'title' => 'Attiva',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'deleted_at' => null,
        ]);
        $deletedId = $this->insertRow('notifications', [
            'title' => 'Soft deleted',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'deleted_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
        ]);

        $results = $this->service->executeAll(false);

        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['affected']);

        $activeCount = (int) $this->pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $activeId)->fetchColumn();
        $deletedCount = (int) $this->pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $deletedId)->fetchColumn();

        $this->assertSame(1, $activeCount);
        $this->assertSame(0, $deletedCount);
    }
}
