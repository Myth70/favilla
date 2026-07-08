<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\HealthCheck\Services\HealthCheckService;

/**
 * CLI command: health:check
 *
 * Esegue tutti i controlli di salute (inclusi i "deep": DNS email, esposizione
 * .env, vulnerabilità dipendenze), salva il run nello storico e invia la notifica
 * admin in caso di fallimenti. Pensato per CI/deploy e per lo Scheduler.
 *
 * Exit code: 0 se nessun check è fallito, 1 se almeno uno è fallito (gli avvisi
 * non fanno fallire). Utilizzabile come gate in pipeline.
 *
 * Usage:
 *   php favilla health:check
 *   php favilla health:check --quiet   (stampa solo il riepilogo)
 */
class HealthCheckCommand
{
    public function handle(array $args): void
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();

        require_once $basePath . '/bootstrap/app.php';

        // Application::boot() non registra Router/ModuleLoader (lo fa solo
        // handleRequest(), mai invocato in CLI): senza questo, notifyOnFailures()
        // va in crash su route('healthcheck.index') non appena c'è un check 'fail'.
        CliBootstrap::boot();

        $quiet = in_array('--quiet', $args, true);

        $service = app(HealthCheckService::class);
        $results = $service->runAll();
        $summary = $service->summary($results);

        $service->saveRun($results, $summary);
        $service->notifyOnFailures($results, $summary);

        echo '=== Health Check ===' . PHP_EOL . PHP_EOL;

        if (!$quiet) {
            $this->printGroups($results);
        }

        echo PHP_EOL;
        echo "Riepilogo: {$summary['ok']} ok · {$summary['warn']} avvisi · {$summary['fail']} falliti" . PHP_EOL;

        if ($summary['fail'] > 0) {
            // Throw → Console::run() ritorna exit code 1 (gate CI/deploy).
            throw new \RuntimeException(
                "Health check fallito: {$summary['fail']} controllo/i non superato/i. " . $this->failList($results)
            );
        }
    }

    /**
     * @param array<string,array{label:string,description:string,checks:array}> $results
     */
    private function printGroups(array $results): void
    {
        $icons = ['ok' => '[OK]', 'warn' => '[!! ]', 'fail' => '[XX]'];

        foreach ($results as $group) {
            echo '— ' . $group['label'] . PHP_EOL;
            foreach ($group['checks'] as $check) {
                $icon = $icons[$check['status']] ?? '[??]';
                echo '  ' . $icon . ' ' . $check['name'] . ': ' . $check['detail'] . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }

    /**
     * @param array<string,array{label:string,description:string,checks:array}> $results
     */
    private function failList(array $results): string
    {
        $names = [];
        foreach ($results as $group) {
            foreach ($group['checks'] as $check) {
                if ($check['status'] === 'fail') {
                    $names[] = $check['name'];
                }
            }
        }

        return implode(', ', $names);
    }
}
