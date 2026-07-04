<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use PDO;

/**
 * Garbage-collect expired database-backed PHP sessions.
 *
 * Usage: php favilla session:gc [--lifetime=7200]
 */
class SessionGcCommand
{
    public function handle(array $args): void
    {
        $this->execute($args);
    }

    public function execute(array $args): int
    {
        $lifetime = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--lifetime=')) {
                $lifetime = max(60, (int) substr($arg, strlen('--lifetime=')));
            }
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad(); // run on pure-env config too (Docker), no .env required

        $cfg = require $basePath . '/app/Config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);

        // Default: use session.gc_maxlifetime from app config, or 7200s
        if ($lifetime === null) {
            $appCfg = require $basePath . '/app/Config/app.php';
            $lifetime = (int) ($appCfg['session']['lifetime'] ?? 480) * 60;
            if ($lifetime < 60) {
                $lifetime = 7200;
            }
        }

        $cutoff = time() - $lifetime;
        $stmt = $pdo->prepare('DELETE FROM php_sessions WHERE last_activity < ?');
        $stmt->execute([$cutoff]);
        $deleted = $stmt->rowCount();

        echo "[OK] Sessioni PHP DB scadute rimosse: {$deleted}\n";

        return 0;
    }
}
