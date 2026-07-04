<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ClientIp;
use PDO;

/**
 * Database-backed PHP session handler.
 * Stores sessions in the `sessions` table with user/IP metadata.
 *
 * Implements \SessionHandlerInterface so it can be registered via
 * session_set_save_handler().
 */
class DatabaseSessionHandler implements \SessionHandlerInterface
{
    private const TABLE = 'php_sessions';

    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT payload FROM ' . self::TABLE . ' WHERE id = ? AND last_activity >= ?'
        );
        $stmt->execute([$id, time() - $this->getLifetime()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return '';
        }

        $decoded = base64_decode((string) $row['payload'], true);

        return $decoded === false ? '' : $decoded;
    }

    public function write(string $id, string $data): bool
    {
        $userId  = $_SESSION['user_id'] ?? null;
        $ip      = ClientIp::resolve();
        $ua      = isset($_SERVER['HTTP_USER_AGENT'])
                   ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                   : null;
        $payload = base64_encode($data);
        $now     = time();

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = '
                INSERT INTO ' . self::TABLE . ' (id, user_id, ip_address, user_agent, payload, last_activity)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(id) DO UPDATE SET
                    payload = excluded.payload,
                    last_activity = excluded.last_activity,
                    user_id = COALESCE(excluded.user_id, ' . self::TABLE . '.user_id),
                    ip_address = COALESCE(excluded.ip_address, ' . self::TABLE . '.ip_address),
                    user_agent = COALESCE(excluded.user_agent, ' . self::TABLE . '.user_agent)
            ';
        } else {
            $sql = '
                INSERT INTO ' . self::TABLE . ' (id, user_id, ip_address, user_agent, payload, last_activity)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    payload = VALUES(payload),
                    last_activity = VALUES(last_activity),
                    user_id = COALESCE(VALUES(user_id), user_id),
                    ip_address = COALESCE(VALUES(ip_address), ip_address),
                    user_agent = COALESCE(VALUES(user_agent), user_agent)
            ';
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$id, $userId, $ip, $ua, $payload, $now]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE last_activity < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }

    private function getLifetime(): int
    {
        return (int) ini_get('session.gc_maxlifetime') ?: 7200;
    }
}
