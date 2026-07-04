<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Services\DataRetentionService;

/**
 * ISO 27001 A.18.1.3 — Execute data retention policies.
 *
 * Usage:
 *   php favilla retention:run              Execute all enabled policies
 *   php favilla retention:run --dry-run    Preview without deleting
 */
class RetentionRunCommand
{
    public function handle(array $args): void
    {
        $dryRun = in_array('--dry-run', $args, true);

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        // Load environment
        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad(); // run on pure-env config too (Docker), no .env required

        // Boot application container
        require_once $basePath . '/bootstrap/app.php';

        $service = app(DataRetentionService::class);

        echo "=== Data Retention (ISO 27001 A.18.1.3) ===\n";
        if ($dryRun) {
            echo "[DRY-RUN] Nessuna eliminazione verrà effettuata.\n";
        }
        echo "\n";

        $results = $service->executeAll($dryRun);

        if (empty($results)) {
            echo "[OK] Nessuna policy attiva o nessun dato da elaborare.\n";
            return;
        }

        $totalAffected = 0;
        $errorCount = 0;
        foreach ($results as $r) {
            if (!empty($r['error'])) {
                echo "[ERRORE] {$r['entity']}: {$r['error']}\n";
                $errorCount++;
                continue;
            }

            $status = $r['dry_run'] ? '[PREVIEW]' : '[OK]';
            $action = $r['action'] === 'anonymize' ? 'Anonimizzati' : 'Eliminati';
            echo "{$status} {$r['entity']}: {$action} {$r['affected']} record (cutoff: {$r['cutoff']})\n";
            $totalAffected += $r['affected'];
        }

        echo "\nTotale: {$totalAffected} record elaborati su " . count($results) . " policy.\n";
        if ($errorCount > 0) {
            echo "Errori: {$errorCount} policy non elaborate correttamente.\n";
            throw new \RuntimeException("Data retention completata con {$errorCount} errori.");
        }
    }
}
