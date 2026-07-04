<?php

namespace Tests\Unit\Services;

use App\Services\DatabaseSessionHandler;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseSessionHandlerTest extends TestCase
{
    private PDO $pdo;
    private DatabaseSessionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE php_sessions (
                id            VARCHAR(128) NOT NULL PRIMARY KEY,
                user_id       INTEGER      NULL,
                ip_address    VARCHAR(45)  NULL,
                user_agent    TEXT         NULL,
                payload       TEXT         NOT NULL,
                last_activity INTEGER      NOT NULL
            )
        ');

        $this->handler = new DatabaseSessionHandler($this->pdo);

        // Set high gc_maxlifetime so reads don't expire during tests
        ini_set('session.gc_maxlifetime', '7200');
    }

    public function test_open_returns_true(): void
    {
        $this->assertTrue($this->handler->open('', 'test'));
    }

    public function test_close_returns_true(): void
    {
        $this->assertTrue($this->handler->close());
    }

    public function test_write_and_read_session(): void
    {
        $data = serialize(['user_id' => 1, 'name' => 'Test']);
        $this->handler->write('sess-001', $data);

        $result = $this->handler->read('sess-001');
        $this->assertSame($data, $result);
    }

    public function test_read_nonexistent_returns_empty_string(): void
    {
        $result = $this->handler->read('nonexistent');
        $this->assertSame('', $result);
    }

    public function test_write_updates_existing_session(): void
    {
        $this->handler->write('sess-002', serialize(['v' => 1]));
        $this->handler->write('sess-002', serialize(['v' => 2]));

        $result = unserialize($this->handler->read('sess-002'));
        $this->assertSame(2, $result['v']);
    }

    public function test_destroy_removes_session(): void
    {
        $this->handler->write('sess-del', 'data');
        $this->handler->destroy('sess-del');

        $this->assertSame('', $this->handler->read('sess-del'));
    }

    public function test_gc_removes_expired_sessions(): void
    {
        // Insert an old session directly
        $stmt = $this->pdo->prepare(
            'INSERT INTO php_sessions (id, payload, last_activity) VALUES (?, ?, ?)'
        );
        $stmt->execute(['old-sess', base64_encode('old'), time() - 10000]);

        // Insert a fresh session
        $this->handler->write('new-sess', 'new');

        // GC with 1 second lifetime — should remove old, keep new
        $deleted = $this->handler->gc(1);

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertSame('', $this->handler->read('old-sess'));
        $this->assertNotEmpty($this->handler->read('new-sess'));
    }

    public function test_read_expired_session_returns_empty(): void
    {
        // Insert session with very old last_activity
        $stmt = $this->pdo->prepare(
            'INSERT INTO php_sessions (id, payload, last_activity) VALUES (?, ?, ?)'
        );
        $stmt->execute(['expired', base64_encode('data'), time() - 100000]);

        $this->assertSame('', $this->handler->read('expired'));
    }

    public function test_write_stores_metadata(): void
    {
        $_SESSION['user_id'] = 42;
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $this->handler->write('sess-meta', 'payload');

        $stmt = $this->pdo->prepare('SELECT user_id, ip_address, user_agent FROM php_sessions WHERE id = ?');
        $stmt->execute(['sess-meta']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(42, (int) $row['user_id']);
        $this->assertSame('192.168.1.1', $row['ip_address']);
        $this->assertSame('TestBrowser/1.0', $row['user_agent']);

        // Cleanup superglobals
        unset($_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function test_write_preserves_user_id_when_session_not_initialized(): void
    {
        $_SESSION['user_id'] = 5;
        $this->handler->write('sess_abc', 'data1');

        unset($_SESSION['user_id']);
        $this->handler->write('sess_abc', 'data2');

        $stmt = $this->pdo->query("SELECT user_id FROM php_sessions WHERE id = 'sess_abc'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(5, (int) $row['user_id']);
    }
}
