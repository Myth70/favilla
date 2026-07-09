<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use PDO;

class CleanupCommand
{
    public function handle(array $args): void
    {
        $this->execute($args);
    }

    public function execute(array $args): int
    {
        $days = 30;
        $dryRun = in_array('--dry-run', $args, true);

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--days=')) {
                $days = max(1, (int) substr($arg, strlen('--days=')));
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

        echo "=== Favilla Cleanup (retention: {$days} giorni) ===\n\n";

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $totalDeleted = 0;

        $totalDeleted += $this->cleanTable(
            $pdo,
            'sessions',
            'expires_at < NOW() OR last_activity < ?',
            [$cutoff],
            'Sessioni applicative scadute',
            $dryRun
        );

        $totalDeleted += $this->cleanTable(
            $pdo,
            'php_sessions',
            'last_activity < ?',
            [time() - ($days * 86400)],
            'Sessioni PHP DB scadute',
            $dryRun
        );

        $totalDeleted += $this->cleanTable(
            $pdo,
            'login_attempts',
            'created_at < ?',
            [$cutoff],
            'Tentativi di login vecchi',
            $dryRun
        );

        $totalDeleted += $this->cleanTable(
            $pdo,
            'password_resets',
            'expires_at < NOW() OR used_at IS NOT NULL',
            [],
            'Token reset password scaduti/usati',
            $dryRun
        );

        $totalDeleted += $this->cleanTable(
            $pdo,
            'notifications',
            'deleted_at IS NOT NULL AND deleted_at < ?',
            [$cutoff],
            'Notifiche cancellate',
            $dryRun
        );

        $totalDeleted += $this->cleanTable(
            $pdo,
            'personal_access_tokens',
            '(revoked_at IS NOT NULL AND revoked_at < ?) OR (expires_at IS NOT NULL AND expires_at < ?)',
            [$cutoff, $cutoff],
            'Token API revocati/scaduti',
            $dryRun
        );

        echo "\n";
        if ($dryRun) {
            echo "[DRY-RUN] Verrebbero eliminati {$totalDeleted} record totali.\n";
        } else {
            echo "Eliminati {$totalDeleted} record totali.\n";
        }

        return 0;
    }

    private function cleanTable(
        PDO $pdo,
        string $table,
        string $where,
        array $params,
        string $label,
        bool $dryRun
    ): int {
        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
            $countStmt->execute($params);
            $count = (int) $countStmt->fetchColumn();

            $prefix = $dryRun ? '[DRY-RUN] ' : '';
            echo "  {$prefix}{$label}: {$count} record";

            if (!$dryRun && $count > 0) {
                $deleteStmt = $pdo->prepare("DELETE FROM {$table} WHERE {$where}");
                $deleteStmt->execute($params);
                echo ' (eliminati)';
            }

            echo "\n";
            return $count;
        } catch (\Throwable $e) {
            echo "  [SKIP] {$label}: tabella non trovata o errore\n";
            return 0;
        }
    }
}
