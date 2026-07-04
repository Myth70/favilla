<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use PDO;

class RateLimitCleanupCommand
{
    public function handle(array $args): void
    {
        $this->execute($args);
    }

    /**
     * Pulisce le entry scadute dalla tabella rate_limits.
     *
     * Usage: php favilla ratelimit:cleanup [--dry-run]
     */
    public function execute(array $args): int
    {
        $dryRun = in_array('--dry-run', $args, true);

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

        echo "=== Rate Limit Cleanup ===\n\n";

        // Count expired entries
        $stmt = $pdo->query('SELECT COUNT(*) FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $expired = (int) $stmt->fetchColumn();

        // Count total entries
        $stmt = $pdo->query('SELECT COUNT(*) FROM rate_limits');
        $total = (int) $stmt->fetchColumn();

        echo "  Totale entry: {$total}\n";
        echo "  Entry scadute (>1 ora): {$expired}\n";

        if ($dryRun) {
            echo "\n[DRY-RUN] Verrebbero eliminate {$expired} entry.\n";
        } elseif ($expired > 0) {
            $pdo->exec('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            echo "\n  Eliminate {$expired} entry scadute.\n";
        } else {
            echo "\n  Nessuna entry da eliminare.\n";
        }

        // Also clean login_attempts older than 24 hours
        $stmt = $pdo->query('SELECT COUNT(*) FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $oldAttempts = (int) $stmt->fetchColumn();

        if ($oldAttempts > 0) {
            echo "  Login attempts vecchi (>24h): {$oldAttempts}";
            if (!$dryRun) {
                $pdo->exec('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
                echo ' (eliminati)';
            }
            echo "\n";
        }

        return 0;
    }
}
