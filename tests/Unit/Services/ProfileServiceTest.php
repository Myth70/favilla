<?php

namespace Tests\Unit\Services;

use App\Services\ProfileService;
use Tests\ModuleTestCase;

class ProfileServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                ip TEXT DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                last_activity TEXT DEFAULT NULL,
                expires_at TEXT DEFAULT NULL
            )
        ');
        $this->migrate("
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT,
                ip_address TEXT,
                success INTEGER,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");
        $this->migrate("
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT,
                entity TEXT,
                entity_id INTEGER,
                ip TEXT,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");
    }

    public function test_parse_user_agent_detects_chrome_windows(): void
    {
        $svc = new ProfileService();
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
        $info = $svc->parseUserAgent($ua);
        $this->assertSame('Google Chrome', $info['browser']);
        $this->assertSame('Windows', $info['os']);
    }

    public function test_parse_user_agent_detects_firefox_linux(): void
    {
        $svc = new ProfileService();
        $ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $info = $svc->parseUserAgent($ua);
        $this->assertSame('Mozilla Firefox', $info['browser']);
        $this->assertSame('Linux', $info['os']);
    }

    public function test_parse_user_agent_detects_edge(): void
    {
        $svc = new ProfileService();
        $ua = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0';
        $info = $svc->parseUserAgent($ua);
        $this->assertSame('Microsoft Edge', $info['browser']);
    }

    public function test_parse_user_agent_detects_safari_macos(): void
    {
        $svc = new ProfileService();
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605 Version/17 Safari/605.1.15';
        $info = $svc->parseUserAgent($ua);
        $this->assertSame('Safari', $info['browser']);
        $this->assertSame('macOS', $info['os']);
    }

    public function test_parse_user_agent_detects_android(): void
    {
        $svc = new ProfileService();
        $ua = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537 Chrome/120';
        $info = $svc->parseUserAgent($ua);
        $this->assertSame('Android', $info['os']);
    }

    public function test_parse_user_agent_detects_ios(): void
    {
        $svc = new ProfileService();
        // NOTE: iPhone UAs traditionally contain "like Mac OS X" which matches
        // the macOS branch first. Test with a UA that does not mention Mac OS X.
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605';
        $info = $svc->parseUserAgent($ua);
        $this->assertSame('iOS', $info['os']);
    }

    public function test_parse_user_agent_unknown_fallback(): void
    {
        $svc = new ProfileService();
        $info = $svc->parseUserAgent('curl/8.0');
        $this->assertSame('Browser sconosciuto', $info['browser']);
        $this->assertSame('', $info['os']);
    }

    public function test_get_active_sessions_filters_expired_and_parses_ua(): void
    {
        $this->insertRow('sessions', [
            'user_id' => 1,
            'ip' => '1.1.1.1',
            'user_agent' => 'Mozilla/5.0 Firefox/120',
            'last_activity' => '2026-04-20 10:00:00',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        $this->insertRow('sessions', [
            'user_id' => 1,
            'ip' => '2.2.2.2',
            'user_agent' => '',
            'last_activity' => '2026-04-19 09:00:00',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $sessions = (new ProfileService())->getActiveSessions(1);
        $this->assertCount(1, $sessions);
        $this->assertArrayHasKey('parsed_ua', $sessions[0]);
        $this->assertSame('Mozilla Firefox', $sessions[0]['parsed_ua']['browser']);
    }

    public function test_revoke_session_returns_true_only_when_match(): void
    {
        $id = $this->insertRow('sessions', [
            'user_id' => 1,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        $svc = new ProfileService();

        $this->assertFalse($svc->revokeSession(2, $id));
        $this->assertTrue($svc->revokeSession(1, $id));
        $this->assertFalse($svc->revokeSession(1, $id));
    }

    public function test_revoke_all_sessions_returns_count(): void
    {
        $this->insertRow('sessions', ['user_id' => 1, 'expires_at' => '2030-01-01']);
        $this->insertRow('sessions', ['user_id' => 1, 'expires_at' => '2030-01-01']);
        $this->insertRow('sessions', ['user_id' => 2, 'expires_at' => '2030-01-01']);

        $svc = new ProfileService();
        $this->assertSame(2, $svc->revokeAllSessions(1));
        $this->assertSame(0, $svc->revokeAllSessions(1));
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
    }

    public function test_get_login_history_orders_desc_and_respects_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->pdo->prepare('INSERT INTO login_attempts (email, ip_address, success, created_at) VALUES (?, ?, ?, ?)')
                ->execute(['u@x.com', '1.1.1.1', $i % 2, "2026-04-2{$i} 10:00:00"]);
        }

        $rows = (new ProfileService())->getLoginHistory('u@x.com', 3);
        $this->assertCount(3, $rows);
        // Most recent first
        $this->assertSame('2026-04-25 10:00:00', $rows[0]['created_at']);
    }

    public function test_get_recent_activity_attaches_meta(): void
    {
        $this->insertRow('audit_logs', [
            'user_id' => 1, 'action' => 'login', 'entity' => 'user', 'entity_id' => 1,
            'ip' => '1.1.1.1', 'created_at' => '2026-04-20 10:00:00',
        ]);
        $this->insertRow('audit_logs', [
            'user_id' => 1, 'action' => 'unknown_action_xyz', 'entity' => 'user', 'entity_id' => 1,
            'ip' => '1.1.1.1', 'created_at' => '2026-04-20 11:00:00',
        ]);

        $rows = (new ProfileService())->getRecentActivity(1, 10);
        $this->assertCount(2, $rows);
        // Most recent first (the unknown one)
        $this->assertSame('Unknown action xyz', $rows[0]['meta']['label']);
        $this->assertSame('fa-solid fa-circle-dot', $rows[0]['meta']['icon']);
        // Known action mapped
        $this->assertSame('Accesso effettuato', $rows[1]['meta']['label']);
    }
}
