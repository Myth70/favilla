<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Services\ModuleDatabaseResolver;

class MakeModuleCommand
{
    private string $stubsDir;
    private string $modulesDir;

    public function __construct()
    {
        $this->stubsDir  = BASE_PATH . '/app/Modules/_Template/stubs';
        $this->modulesDir = BASE_PATH . '/app/Modules';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? '';

        if (!$this->validateName($name)) {
            return;
        }

        // ── Parse opzionali --db / --db-name / --no-provision ────────
        $dbMode      = 'shared';
        $dbName      = null;
        $noProvision = false;

        foreach (array_slice($args, 1) as $arg) {
            if ($arg === '--no-provision') {
                $noProvision = true;
                continue;
            }
            if (str_starts_with($arg, '--db=')) {
                $val = substr($arg, strlen('--db='));
                if (!in_array($val, ['shared', 'independent'], true)) {
                    $this->error("Valore --db non valido: '{$val}'. Usa 'shared' o 'independent'.");
                    return;
                }
                $dbMode = $val;
                continue;
            }
            if (str_starts_with($arg, '--db-name=')) {
                $dbName = substr($arg, strlen('--db-name='));
                continue;
            }
        }

        if ($dbMode === 'shared' && ($dbName !== null || $noProvision)) {
            $this->warn('--db-name e --no-provision si applicano solo a --db=independent: ignorati.');
            $dbName      = null;
            $noProvision = false;
        }

        $targetDir = $this->modulesDir . '/' . $name;
        if (is_dir($targetDir)) {
            $this->error("Il modulo \"{$name}\" esiste gia' in app/Modules/.");
            return;
        }

        $this->createModule($name, $targetDir, $dbMode, $dbName, $noProvision);
    }

    private function validateName(string $name): bool
    {
        if ($name === '') {
            $this->error('Uso: php favilla make:module <NomeModulo> [--db=shared|independent] [--db-name=...] [--no-provision]');
            return false;
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9]+$/', $name)) {
            $this->error("Nome non valido: \"{$name}\". Deve essere PascalCase (es. Clienti, MateriePrime).");
            return false;
        }
        return true;
    }

    private function createModule(
        string $name,
        string $targetDir,
        string $dbMode,
        ?string $dbName,
        bool $noProvision
    ): void {
        $lower     = strtolower($name);
        $namespace = 'App\\Modules\\' . $name;
        $table     = $lower;
        $year      = date('Y');

        // ── Provisioning DB (prima di toccare il filesystem) ─────────
        $resolver         = null;
        $resolvedDbName   = null;
        $provisionStatus  = null;

        if ($dbMode === 'independent') {
            try {
                $resolver = app(ModuleDatabaseResolver::class);
            } catch (\Throwable $e) {
                $this->error('ModuleDatabaseResolver non disponibile: ' . $e->getMessage());
                return;
            }

            $resolvedDbName = $dbName ?? $resolver->suggestName($name);

            try {
                $resolver->validateName($resolvedDbName);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                return;
            }

            try {
                if ($noProvision) {
                    $resolver->markManual($name, $resolvedDbName);
                    $provisionStatus = 'manual';
                } else {
                    $resolver->provision($name, $resolvedDbName);
                    $provisionStatus = 'ready';
                }
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $this->warn('Suggerimento: rilancia con --no-provision per registrare il mapping senza creare il DB (utile in hosting condivisi).');
                return;
            }
        }

        $placeholders = [
            '{{ModuleName}}'      => $name,
            '{{module_name}}'     => $lower,
            '{{module-name}}'     => str_replace('_', '-', $lower),
            '{{ModuleNamespace}}' => $namespace,
            '{{TableName}}'       => $table,
            '{{year}}'            => $year,
        ];

        // Stub Repository: scegli variante in base alla modalita' DB
        $repoStub = ($dbMode === 'independent') ? 'Repository.independent.stub' : 'Repository.stub';

        // Mappa stub → path relativo nel modulo
        $files = [
            'Controller.stub'                    => "Controllers/{$name}Controller.php",
            'Service.stub'                       => "Services/{$name}Service.php",
            $repoStub                            => "Repositories/{$name}Repository.php",
            'routes.stub'                        => 'routes.php',
            'permissions.stub'                   => 'permissions.php',
            'module.json.stub'                   => 'module.json',
            'migration.stub'                     => "migrations/001_{$lower}.sql",
            'Views/index.stub'                   => 'Views/index.php',
            'Views/form.stub'                    => 'Views/form.php',
            'Views/show.stub'                    => 'Views/show.php',
            'Views/partials/table.stub'          => 'Views/partials/table.php',
            'Views/partials/search-results.stub' => 'Views/partials/search-results.php',
            'Tests/RepositoryTest.stub'          => "Tests/Unit/{$name}RepositoryTest.php",
            'Tests/ServiceTest.stub'             => "Tests/Unit/{$name}ServiceTest.php",
            'Tests/ControllerTest.stub'          => "Tests/Unit/{$name}ControllerTest.php",
            'Tests/RoutesTest.stub'              => "Tests/Unit/{$name}RoutesTest.php",
        ];

        $created = [];
        foreach ($files as $stub => $target) {
            $stubPath   = $this->stubsDir . '/' . $stub;
            $targetPath = $targetDir . '/' . $target;

            if (!file_exists($stubPath)) {
                $this->warn("Stub mancante: {$stub} — saltato.");
                continue;
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = file_get_contents($stubPath);
            $content = str_replace(array_keys($placeholders), array_values($placeholders), $content);
            file_put_contents($targetPath, $content);
            $created[] = $target;
        }

        // Patch module.json con database mode + suggested name
        $jsonPath = $targetDir . '/module.json';
        if (file_exists($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true) ?: [];
            $meta['database']                = $dbMode;
            $meta['database_suggested_name'] = $resolvedDbName;
            file_put_contents(
                $jsonPath,
                json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        // Genera i file lingua: it canonico + copie per ogni altra locale presente
        // in resources/lang/ (en/fr/de/es). Le copie vanno tradotte; lang:check
        // resta verde perché tutte le locale hanno le stesse chiavi.
        $langCreated = $this->createLangFiles($lower, $placeholders);

        $this->success("Modulo \"{$name}\" creato con successo!");
        echo "\nFile creati:\n";
        foreach ($created as $f) {
            echo "  app/Modules/{$name}/{$f}\n";
        }
        if (!empty($langCreated)) {
            echo "\nFile lingua creati:\n";
            foreach ($langCreated as $f) {
                echo "  {$f}\n";
            }
        }

        if ($dbMode === 'independent') {
            echo "\nDatabase dedicato:\n";
            echo "  Nome:   {$resolvedDbName}\n";
            echo "  Stato:  {$provisionStatus}";
            if ($provisionStatus === 'manual') {
                echo '  (creare il database manualmente con i comandi qui sotto)';
            }
            echo "\n";
            if ($provisionStatus === 'manual') {
                echo "\nSQL suggerito:\n";
                echo "  CREATE DATABASE `{$resolvedDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
            }
        }

        echo "\nPassi manuali:\n";
        echo "  1. Personalizza migrations/001_{$lower}.sql, routes.php, permissions.php\n";
        echo '  2. Esegui: php database/migrate.php' . ($dbMode === 'independent' ? " --module={$name}" : '') . "\n";
        echo "  3. Logout + Login per ricaricare i permessi\n";
        echo "  4. Rigenera il contesto: php favilla context:generate\n";
        echo "  5. Traduci resources/lang/{en,fr,de,es}/{$lower}.php e verifica: php favilla lang:check\n";
        echo "  6. Esegui i test generati: vendor/bin/phpunit --filter {$name}\n";
        echo "\nNota: il modulo e' gia' rilevato automaticamente via module.json (auto-discovery).\n";
        echo "      Non occorre modificare app/Config/modules.php.\n\n";
    }

    /**
     * Genera i file di lingua del modulo da stubs/lang.stub.
     *
     * Scrive resources/lang/it/<modulo>.php (italiano canonico) e una copia
     * identica in ogni altra locale presente in resources/lang/ (en/fr/de/es),
     * da tradurre. Non sovrascrive file lingua già esistenti.
     *
     * @param array<string,string> $placeholders
     * @return string[] Percorsi (relativi a BASE_PATH) dei file creati.
     */
    private function createLangFiles(string $lower, array $placeholders): array
    {
        $stubPath = $this->stubsDir . '/lang.stub';
        if (!file_exists($stubPath)) {
            $this->warn('Stub lingua mancante: lang.stub — file lingua non generati.');
            return [];
        }

        $content = str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            (string) file_get_contents($stubPath)
        );

        $langRoot = BASE_PATH . '/resources/lang';
        $created  = [];

        foreach ($this->detectLocales($langRoot) as $locale) {
            $dir    = $langRoot . '/' . $locale;
            $target = $dir . '/' . $lower . '.php';

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (file_exists($target)) {
                $this->warn("File lingua già esistente: resources/lang/{$locale}/{$lower}.php — saltato.");
                continue;
            }

            file_put_contents($target, $content);
            $created[] = "resources/lang/{$locale}/{$lower}.php";
        }

        return $created;
    }

    /**
     * Locale di destinazione: le sottocartelle esistenti di resources/lang/
     * (così il set segue il progetto). 'it' è sempre incluso come baseline.
     *
     * @return string[]
     */
    private function detectLocales(string $langRoot): array
    {
        $locales = [];
        if (is_dir($langRoot)) {
            foreach ((array) glob($langRoot . '/*', GLOB_ONLYDIR) as $dir) {
                $locales[] = basename((string) $dir);
            }
        }
        if (!in_array('it', $locales, true)) {
            array_unshift($locales, 'it');
        }
        return $locales;
    }

    private function success(string $msg): void
    {
        echo "\033[32m[OK]\033[0m {$msg}\n";
    }
    private function error(string $msg): void
    {
        echo "\033[31m[ERR]\033[0m {$msg}\n";
    }
    private function warn(string $msg): void
    {
        echo "\033[33m[WARN]\033[0m {$msg}\n";
    }
}
