<?php

declare(strict_types=1);

namespace App\Cli\Commands;

class ModuleStatusCommand
{
    public function handle(array $args): void
    {
        $modules = $this->discoverModules();
        $executed = $this->executedMigrations();

        echo "\nStato moduli Favilla\n";
        echo "=====================\n\n";
        printf("%-20s %-10s %-12s %s\n", 'MODULO', 'VERSIONE', 'MIGRATION', 'STATO');
        echo str_repeat('-', 60) . "\n";

        foreach ($modules as $mod) {
            $name    = $mod['name'];
            $version = $mod['version'];
            $total   = $mod['total_migrations'];
            $done    = $mod['executed_migrations'];

            if ($total === 0) {
                $status = '— nessuna migration';
            } elseif ($done >= $total) {
                $status = "\033[32m✓ OK\033[0m";
            } else {
                $status = "\033[33m⚠ PENDING (" . ($total - $done) . " da eseguire)\033[0m";
            }

            $migStr = $total > 0 ? "{$done}/{$total}" : '—';
            printf("%-20s %-10s %-12s %s\n", $name, $version, $migStr, $status);
        }

        $pending = array_filter($modules, fn ($m) => $m['total_migrations'] > 0 && $m['executed_migrations'] < $m['total_migrations']);
        if (!empty($pending)) {
            echo "\nEsegui: php database/migrate.php per applicare le migration pendenti.\n";
        }
        echo "\n";
    }

    private function discoverModules(): array
    {
        $modulesDir = BASE_PATH . '/app/Modules';
        $results    = [];

        foreach (glob($modulesDir . '/*/module.json') as $jsonFile) {
            $dir  = dirname($jsonFile);
            $name = basename($dir);

            if ($name === '_Template') {
                continue;
            }

            $meta    = json_decode(file_get_contents($jsonFile), true) ?? [];
            $version = $meta['version'] ?? '—';

            $migDir  = $dir . '/migrations';
            $total   = is_dir($migDir) ? count(glob($migDir . '/*.sql')) : 0;

            $results[] = [
                'name'                => $name,
                'version'             => $version,
                'total_migrations'    => $total,
                'executed_migrations' => 0, // filled below
            ];
        }

        // Core (no module.json)
        $results[] = [
            'name'                => 'Core',
            'version'             => '(core)',
            'total_migrations'    => count(glob(BASE_PATH . '/database/migrations/*.sql')),
            'executed_migrations' => 0,
        ];

        // Fill executed counts from DB
        $executed = $this->executedMigrations();
        foreach ($results as &$mod) {
            $key = $mod['name'] === 'Core' ? null : $mod['name'];
            $mod['executed_migrations'] = $executed[$key] ?? 0;
        }

        // Sort: Core first, then alphabetically
        usort($results, function ($a, $b) {
            if ($a['name'] === 'Core') {
                return -1;
            }
            if ($b['name'] === 'Core') {
                return 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $results;
    }

    private function executedMigrations(): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST']     ?? 'localhost',
                $_ENV['DB_PORT']     ?? '3306',
                $_ENV['DB_DATABASE'] ?? 'favilla'
            );
            $pdo  = new \PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '');
            $rows = $pdo->query('SELECT module, COUNT(*) as cnt FROM migrations GROUP BY module')->fetchAll(\PDO::FETCH_ASSOC);

            $map = [];
            foreach ($rows as $r) {
                // module NULL = core
                $key       = $r['module'] === '' || $r['module'] === null ? null : $r['module'];
                $map[$key] = (int) $r['cnt'];
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
