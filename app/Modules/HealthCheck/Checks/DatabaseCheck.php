<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\HealthCheck\Repositories\SystemDiagnosticsRepository;
use App\Modules\HealthCheck\Support\PendingMigrations;

/**
 * Disponibilità del database, compatibilità e stato schema.
 */
class DatabaseCheck extends AbstractHealthCheck
{
    private ?SystemDiagnosticsRepository $repo;

    public function __construct(?SystemDiagnosticsRepository $repo = null)
    {
        // Risoluzione lazy: il registro non costruisce il repository finché il check non gira.
        $this->repo = $repo;
    }

    private function repo(): SystemDiagnosticsRepository
    {
        return $this->repo ??= app(SystemDiagnosticsRepository::class);
    }

    public function key(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'Database';
    }

    public function description(): string
    {
        return 'Disponibilita del database, compatibilita e stato schema.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            // La connessione è provata dalla prima query.
            $ver = $this->repo()->databaseVersion();
            $checks[] = $this->ok('Connessione database', 'operativa');
            $checks[] = $this->ok('Versione database', $ver);

            // Versione minima MariaDB >= 10.4
            if (preg_match('/(\d+\.\d+\.\d+)-MariaDB/', $ver, $m)) {
                $checks[] = version_compare($m[1], '10.4.0', '>=')
                    ? $this->ok('Compatibilita MariaDB', $m[1] . ' compatibile')
                    : $this->warn('Compatibilita MariaDB', $m[1] . ' — consigliata almeno 10.4');
            }

            // Charset tabelle
            $nonUtf8 = $this->repo()->tablesNotUtf8mb4(5);
            $checks[] = empty($nonUtf8)
                ? $this->ok('Collation tabelle', 'utf8mb4 rilevato')
                : $this->warn('Collation tabelle', 'Non utf8mb4: ' . implode(', ', $nonUtf8));

            // Connessioni attive vs max_connections
            try {
                $load = $this->repo()->connectionLoad();
                if ($load !== null) {
                    $pct = (int) round(($load['active'] / $load['max']) * 100);
                    $checks[] = $pct <= 80
                        ? $this->ok('Carico connessioni database', "{$load['active']}/{$load['max']} ({$pct}%)")
                        : $this->warn('Carico connessioni database', "{$load['active']}/{$load['max']} ({$pct}%) — oltre la soglia consigliata");
                }
            } catch (\Throwable) {
                // silenzioso
            }

            // Migration pendenti
            $pending = $this->countPendingMigrations();
            $checks[] = $pending === 0
                ? $this->ok('Stato migrazioni', 'allineato')
                : $this->warn('Stato migrazioni', "{$pending} migrazioni da eseguire");
        } catch (\Throwable $e) {
            $checks[] = $this->fail('Connessione database', $e->getMessage());
        }

        return $checks;
    }

    private function countPendingMigrations(): int
    {
        try {
            return PendingMigrations::count(
                $this->repo()->executedCoreMigrations(),
                $this->repo()->executedModuleMigrations()
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
