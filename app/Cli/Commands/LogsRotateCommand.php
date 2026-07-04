<?php

declare(strict_types=1);

namespace App\Cli\Commands;

/**
 * CLI command: logs:rotate
 *
 * Rotates the application log file and purges old rotated files
 * beyond retention. Designed to be run daily via Scheduler.
 *
 * Usage:
 *   php favilla logs:rotate
 *   php favilla logs:rotate --verify
 *   php favilla logs:rotate --purge-only
 */
class LogsRotateCommand
{
    public function handle(array $args): void
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        // Load .env
        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad(); // run on pure-env config too (Docker), no .env required

        // Boot App for DI container
        require_once $basePath . '/bootstrap/app.php';

        $service = app(\App\Services\LogRotationService::class);

        $verifyOnly = in_array('--verify', $args, true);
        $purgeOnly = in_array('--purge-only', $args, true);

        if ($verifyOnly) {
            $this->verify($service);
            return;
        }

        if ($purgeOnly) {
            $this->purge($service);
            return;
        }

        // Full rotation: rotate + purge
        echo '=== Log Rotation (ISO 27001 A.12.4) ===' . PHP_EOL . PHP_EOL;

        // Status before
        $status = $service->getStatus();
        echo 'Log attivo: ' . \App\Services\LogRotationService::humanSize($status['active_size']) . PHP_EOL;
        echo "File ruotati: {$status['rotated_count']} (" . \App\Services\LogRotationService::humanSize($status['rotated_size']) . ')' . PHP_EOL;
        echo "Retention: {$status['retention_days']} giorni" . PHP_EOL . PHP_EOL;

        // Rotate
        $result = $service->rotate();
        if ($result['rotated']) {
            echo "[OK] Ruotato → {$result['file']} (" . \App\Services\LogRotationService::humanSize($result['size']) . ')' . PHP_EOL;
            if ($result['hash']) {
                echo '     HMAC: ' . substr($result['hash'], 0, 16) . '...' . PHP_EOL;
            }
        } else {
            echo '[SKIP] Nessuna rotazione necessaria';
            if ($result['error']) {
                echo " — {$result['error']}";
            }
            echo PHP_EOL;
        }

        // Purge
        $this->purge($service);
    }

    private function purge(\App\Services\LogRotationService $service): void
    {
        echo PHP_EOL . '--- Pulizia file scaduti ---' . PHP_EOL;
        $purgeResult = $service->purge();

        if ($purgeResult['deleted'] > 0) {
            echo "[OK] Eliminati {$purgeResult['deleted']} file (" . \App\Services\LogRotationService::humanSize($purgeResult['freed']) . ' liberati)' . PHP_EOL;
        } else {
            echo '[SKIP] Nessun file da eliminare.' . PHP_EOL;
        }

        foreach ($purgeResult['errors'] as $err) {
            echo "[ERRORE] {$err}" . PHP_EOL;
        }
    }

    private function verify(\App\Services\LogRotationService $service): void
    {
        echo '=== Verifica Integrità Log (ISO 27001 A.12.4) ===' . PHP_EOL . PHP_EOL;

        $result = $service->verifyAll();

        echo "File verificati: {$result['total']}" . PHP_EOL;
        echo "  Validi:         {$result['valid']}" . PHP_EOL;
        echo "  Non validi:     {$result['invalid']}" . PHP_EOL;
        echo "  Senza HMAC:     {$result['missing_hash']}" . PHP_EOL . PHP_EOL;

        foreach ($result['results'] as $r) {
            $icon = $r['valid'] ? '[OK]' : '[!!]';
            echo "  {$icon} {$r['file']}";
            if ($r['error']) {
                echo " — {$r['error']}";
            }
            echo PHP_EOL;
        }

        if ($result['invalid'] > 0) {
            echo PHP_EOL . '[ATTENZIONE] Rilevati file con integrità compromessa!' . PHP_EOL;
        }
    }
}
