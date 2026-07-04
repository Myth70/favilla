<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Modules\Reports\Services\HistoryService;

/**
 * ISO 27001 A.8.2 — Clean up expired report files.
 *
 * Usage: php favilla reports:cleanup
 */
class ReportsCleanupCommand
{
    public function handle(array $args): void
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        // Load environment
        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad(); // run on pure-env config too (Docker), no .env required

        // Boot application container
        require_once $basePath . '/bootstrap/app.php';

        echo "=== Report Cleanup (ISO 27001 A.8.2) ===\n\n";

        $service = app(HistoryService::class);
        $result  = $service->cleanupExpired();

        $count = $result['deleted_count'] ?? 0;
        $freed = $result['freed_bytes'] ?? 0;

        if ($count === 0) {
            echo "[OK] Nessun report scaduto da eliminare.\n";
        } else {
            $freedMb = round($freed / 1048576, 2);
            echo "[OK] Eliminati {$count} report scaduti ({$freedMb} MB liberati).\n";
        }
    }
}
