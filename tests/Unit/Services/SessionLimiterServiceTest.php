<?php

namespace Tests\Unit\Services;

use App\Repositories\SettingsRepository;
use App\Services\SessionLimiterService;
use App\Services\SettingsService;
use Tests\ModuleTestCase;

class SessionLimiterServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                last_activity TEXT DEFAULT (datetime('now')),
                expires_at TEXT DEFAULT (datetime('now', '+1 hour'))
            )
        ");
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
        $this->migrate("
            CREATE TABLE app_settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                type TEXT DEFAULT 'string',
                'group' TEXT DEFAULT 'general',
                label TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ");

        app()->instance(SettingsRepository::class, new SettingsRepository());
        SettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    private function addSession(int $userId, string $lastActivity): int
    {
        return $this->insertRow('sessions', [
            'user_id'       => $userId,
            'last_activity' => $lastActivity,
        ]);
    }

    private function setMaxConcurrent(int $max): void
    {
        $this->pdo->exec("DELETE FROM app_settings WHERE `key` = 'session_max_concurrent'");
        $this->pdo->prepare("INSERT INTO app_settings (`key`, value, type, `group`) VALUES ('session_max_concurrent', ?, 'int', 'security')")
            ->execute([(string) $max]);
        SettingsService::clearCache();
    }

    public function test_enforce_is_noop_when_limit_zero_or_negative(): void
    {
        $this->setMaxConcurrent(0);
        $current = $this->addSession(1, '2026-01-01 10:00:00');
        $this->addSession(1, '2026-01-01 09:00:00');
        $this->addSession(1, '2026-01-01 08:00:00');

        (new SessionLimiterService())->enforce(1, $current);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM sessions WHERE user_id = 1')->fetchColumn();
        $this->assertSame(3, $count);
    }

    public function test_enforce_keeps_within_limit(): void
    {
        $this->setMaxConcurrent(3);
        $current = $this->addSession(1, '2026-01-01 12:00:00');
        $this->addSession(1, '2026-01-01 11:00:00');

        (new SessionLimiterService())->enforce(1, $current);

        // 2 existing + current = 2 others + current = within 3 → untouched
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM sessions WHERE user_id = 1')->fetchColumn());
    }

    public function test_enforce_evicts_oldest_beyond_limit(): void
    {
        $this->setMaxConcurrent(2); // keep 1 other + current
        $current = $this->addSession(1, '2026-01-01 12:00:00');
        $recent  = $this->addSession(1, '2026-01-01 11:00:00');
        $oldest  = $this->addSession(1, '2026-01-01 09:00:00');
        $middle  = $this->addSession(1, '2026-01-01 10:00:00');

        (new SessionLimiterService())->enforce(1, $current);

        $remaining = array_map('intval', $this->pdo->query('SELECT id FROM sessions WHERE user_id = 1 ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
        $this->assertContains($current, $remaining);
        $this->assertContains($recent, $remaining);
        $this->assertNotContains($oldest, $remaining);
        $this->assertNotContains($middle, $remaining);

        // Audit log should record the eviction
        $audit = $this->pdo->query('SELECT action, entity, new_value FROM audit_logs')->fetch();
        $this->assertSame('session_evicted', $audit['action']);
        $this->assertSame('session', $audit['entity']);
        $this->assertStringContainsString('evicted_count', $audit['new_value']);
    }

    public function test_enforce_does_not_touch_other_users_sessions(): void
    {
        $this->setMaxConcurrent(1);
        $current = $this->addSession(1, '2026-01-01 12:00:00');
        $this->addSession(1, '2026-01-01 11:00:00');
        $other = $this->addSession(2, '2026-01-01 01:00:00');

        (new SessionLimiterService())->enforce(1, $current);

        $this->assertNotFalse($this->pdo->query("SELECT id FROM sessions WHERE id = {$other}")->fetchColumn());
    }

    public function test_count_active_returns_only_non_expired(): void
    {
        $this->insertRow('sessions', [
            'user_id' => 1,
            'last_activity' => '2026-01-01 10:00:00',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        $this->insertRow('sessions', [
            'user_id' => 1,
            'last_activity' => '2026-01-01 09:00:00',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->assertSame(1, (new SessionLimiterService())->countActive(1));
    }
}
