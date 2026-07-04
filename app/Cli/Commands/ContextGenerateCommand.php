<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Core\ModuleLoader;
use App\Services\PermissionSyncService;

class ContextGenerateCommand
{
    private string $basePath;
    private string $modulesDir;
    private ?\PDO $pdo = null;

    public function __construct()
    {
        $this->basePath   = BASE_PATH;
        $this->modulesDir = BASE_PATH . '/app/Modules';
    }

    public function handle(array $args): void
    {
        $this->syncPermissions();

        $this->info('Generazione context (indice + dettaglio per-modulo)...');

        $generatedAt = date('c');
        $framework   = $this->buildFrameworkInfo();
        $modules     = $this->buildModules();
        $database    = $this->buildDatabase();
        $routes      = $this->buildRoutes();
        $permissions = $this->buildPermissions();
        $services    = $this->buildServices();
        $assets      = $this->buildAssets();
        $config      = $this->buildConfig();

        $tablesByName = [];
        foreach ($database['tables'] as $t) {
            $tablesByName[$t['name']] = $t;
        }

        $routesByModule = [];
        foreach ($routes as $r) {
            $module = $r['module'] ?? null;
            $key    = ($module === null || $module === '') ? '_core' : $module;
            unset($r['module']); // ridondante: il file e' gia' specifico del modulo
            $routesByModule[$key][] = $r;
        }

        $canonByLower = [];
        foreach ($modules as $m) {
            $canonByLower[strtolower($m['name'])] = $m['name'];
        }
        $permsByModule = [];
        foreach ($permissions as $p) {
            $canon = $canonByLower[strtolower((string) $p['module'])] ?? $p['module'];
            $permsByModule[$canon][] = ['slug' => $p['slug'], 'name' => $p['name']];
        }

        $coreServices = [];
        foreach ($services as $s) {
            if ($s['module'] === 'core') {
                $coreServices[] = $s['class'];
            }
        }

        $claimed = [];
        foreach ($modules as $m) {
            foreach ($m['tables'] as $tname) {
                $claimed[$tname] = true;
            }
        }
        $coreTables = [];
        foreach ($database['tables'] as $t) {
            if (!isset($claimed[$t['name']])) {
                $coreTables[] = $t;
            }
        }

        $contextDir = $this->basePath . '/context';
        if (!is_dir($contextDir)) {
            mkdir($contextDir, 0775, true);
        }

        $totalBytes      = 0;
        $writtenModules  = [];
        $moduleSummaries = [];

        foreach ($modules as $m) {
            $name = $m['name'];

            $modTables = [];
            foreach ($m['tables'] as $tname) {
                if (isset($tablesByName[$tname])) {
                    $modTables[] = $tablesByName[$tname];
                }
            }
            $modRoutes = $routesByModule[$name] ?? [];

            $detail = [
                'module'       => $name,
                'generated_at' => $generatedAt,
                'routes'       => $modRoutes,
                'tables'       => $modTables,
                'permissions'  => $permsByModule[$name] ?? [],
                'services'     => $m['services'],    // FQCN
                'controllers'  => $m['controllers'], // FQCN
            ];

            $relPath = 'context/' . $name . '.json';
            $totalBytes += $this->writeJson($this->basePath . '/' . $relPath, $detail);
            $writtenModules[$name] = true;

            $moduleSummaries[] = [
                'name'                    => $name,
                'version'                 => $m['version'],
                'description'             => $m['description'],
                'core'                    => $m['core'],
                'database_mode'           => $m['database_mode'],
                'database_suggested_name' => $m['database_suggested_name'],
                'database_installed'      => $m['database_installed'],
                'dependencies'            => $m['dependencies'],
                'has_migrations'          => $m['has_migrations'],
                'search_provider'         => $m['search_provider'],
                'export_provider'         => $m['export_provider'],
                'counts'                  => [
                    'routes'      => count($modRoutes),
                    'tables'      => count($m['tables']),
                    'permissions' => count($m['permissions']),
                    'services'    => count($m['services']),
                    'controllers' => count($m['controllers']),
                ],
                'tables'      => $m['tables'],         // solo nomi
                'permissions' => $m['permissions'],    // solo slug
                'controllers' => array_map([$this, 'shortClass'], $m['controllers']),
                'services'    => array_map([$this, 'shortClass'], $m['services']),
                'detail'      => $relPath,
            ];
        }

        $coreDetail = [
            'generated_at' => $generatedAt,
            'tables'       => $coreTables,
            'services'     => $coreServices,
            'routes'       => $routesByModule['_core'] ?? [],
        ];
        $totalBytes += $this->writeJson($contextDir . '/_core.json', $coreDetail);

        $pruned = [];
        foreach (glob($contextDir . '/*.json') as $file) {
            $base = basename($file, '.json');
            if ($base === '_core' || isset($writtenModules[$base])) {
                continue;
            }
            unlink($file);
            $pruned[] = $base;
        }

        $index = [
            'generated_at' => $generatedAt,
            'layout'       => [
                'index'         => 'project_context.json',
                'detail_dir'    => 'context/',
                'core_detail'   => 'context/_core.json',
                'module_detail' => 'context/<Module>.json',
                'note'          => 'Carica sempre questo indice. Apri context/<Module>.json solo quando lavori su quel modulo: route complete, schema tabelle (colonne/indici/FK), label permessi e FQCN di service/controller stanno nei file di dettaglio.',
            ],
            'framework'    => $framework,
            'config'       => $config,
            'assets'       => $assets,
            'permissions'  => $permissions, // catalogo completo (slug/name/module) per lookup cross-modulo
            'modules'      => $moduleSummaries,
            'core'         => [
                'tables'         => array_column($coreTables, 'name'),
                'services_count' => count($coreServices),
                'detail'         => 'context/_core.json',
            ],
        ];

        $indexPath  = $this->basePath . '/project_context.json';
        $indexBytes = $this->writeJson($indexPath, $index);
        $totalBytes += $indexBytes;

        $indexKb = round($indexBytes / 1024, 1);
        $totalKb = round($totalBytes / 1024, 1);
        $modCount = count($moduleSummaries);

        $this->success("Context generato: indice {$indexKb} KB + " . ($modCount + 1) . " file di dettaglio (totale {$totalKb} KB)");
        echo "\n";
        echo "  Indice:     project_context.json ({$indexKb} KB)\n";
        echo "  Dettaglio:  context/_core.json + {$modCount} moduli\n";
        echo '  Route:      ' . count($routes) . "\n";
        echo '  Tabelle:    ' . count($database['tables']) . ' (di cui ' . count($coreTables) . " core/condivise)\n";
        echo '  Permessi:   ' . count($permissions) . "\n";
        if (!empty($pruned)) {
            echo '  Rimossi:    ' . implode(', ', $pruned) . " (moduli non piu' presenti)\n";
        }
        echo "\n";
        echo "  Output: {$indexPath}\n";
        echo "          {$contextDir}/\n\n";
    }

    private function writeJson(string $path, array $data): int
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->error('Errore encoding JSON (' . basename($path) . '): ' . json_last_error_msg());
            return 0; // error() termina comunque l'esecuzione
        }
        file_put_contents($path, $json);
        return strlen($json);
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function syncPermissions(): void
    {
        $pdo = $this->getPdo();
        if (!$pdo) {
            $this->warn('DB non raggiungibile — permission sync saltato.');
            return;
        }

        try {
            $loader = new ModuleLoader($this->basePath);
            $loader->loadConfig();
            $loader->loadDbOverrides($pdo);

            $service = new PermissionSyncService($pdo, $loader);
            $report  = $service->sync();

            $added      = count($report['added']);
            $renamed    = count($report['renamed']);
            $collisions = count($report['collisions']);
            $orphaned   = count($report['orphaned']);

            $this->info("Permission sync: +{$added} nuovi, ~{$renamed} rinominati, {$collisions} collisioni, {$orphaned} orphan");

            if ($added > 0) {
                foreach ($report['added'] as $p) {
                    echo "  + {$p['slug']} ({$p['module']})\n";
                }
            }
            if ($renamed > 0) {
                foreach ($report['renamed'] as $p) {
                    echo "  ~ {$p['slug']}: '{$p['old_name']}' -> '{$p['new_name']}'\n";
                }
            }
            if ($collisions > 0) {
                foreach ($report['collisions'] as $c) {
                    $dup = implode(', ', $c['declared_by']);
                    $this->warn("  ! collisione '{$c['slug']}' dichiarato da: {$dup} (vince {$c['winner_module']})");
                }
            }
            if ($orphaned > 0) {
                foreach ($report['orphaned'] as $o) {
                    $this->warn("  ? orphan '{$o['slug']}' (ex-{$o['module']}) non dichiarato da alcun modulo abilitato — non cancellato");
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Permission sync fallito: ' . $e->getMessage());
        }

        echo "\n";
    }


    private function buildFrameworkInfo(): array
    {
        $composerJson = $this->basePath . '/composer.json';
        $deps         = [];

        if (file_exists($composerJson)) {
            $composer = json_decode(file_get_contents($composerJson), true) ?? [];
            foreach ($composer['require'] ?? [] as $pkg => $ver) {
                if ($pkg !== 'php') {
                    $deps[] = "{$pkg}:{$ver}";
                }
            }
        }

        $version = $this->detectFrameworkVersion();

        return [
            'name'         => 'Favilla',
            'version'      => $version,
            'php_required' => '>=8.2',
            'php_running'  => PHP_VERSION,
            'stack'        => ['PHP 8.2', 'MariaDB 10.4', 'Bootstrap 5.3.3', 'HTMX 2.0.4', 'Font Awesome 6.7.2'],
            'dependencies' => $deps,
        ];
    }

    private function detectFrameworkVersion(): string
    {
        try {
            $pdo = $this->getPdo();
            if (!$pdo) {
                return 'unknown';
            }
            $stmt = $pdo->query('SELECT version FROM changelogs WHERE is_published = 1 ORDER BY release_date DESC, id DESC LIMIT 1');
            $row  = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            return $row ? $row['version'] : 'dev';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }


    private function buildModules(): array
    {
        $modules       = [];
        $modulesCfg    = require $this->basePath . '/app/Config/modules.php';
        $coreByName    = [];

        foreach ($modulesCfg as $entry) {
            $coreByName[$entry['name']] = (bool) ($entry['core'] ?? false);
        }

        // Scan filesystem
        foreach (glob($this->modulesDir . '/*/') as $dir) {
            $name = basename($dir);
            if ($name === '_Template') {
                continue;
            }

            $meta = [];
            $jsonFile = $dir . 'module.json';
            if (file_exists($jsonFile)) {
                $meta = json_decode(file_get_contents($jsonFile), true) ?? [];
            }

            $perms = $this->loadModulePermissions($name);

            $dbMapping = $this->loadModuleDbMapping($name);

            $modules[] = [
                'name'                    => $name,
                'version'                 => $meta['version'] ?? null,
                'description'             => $meta['description'] ?? null,
                'core'                    => $coreByName[$name] ?? false,
                'database_mode'           => $meta['database'] ?? 'shared',
                'database_suggested_name' => $meta['database_suggested_name'] ?? null,
                'database_installed'      => $dbMapping,
                'tables'                  => $meta['tables'] ?? [],
                'dependencies'            => $meta['dependencies'] ?? [],
                'permissions'             => array_column($perms, 'slug'),
                'search_provider'         => $meta['search_provider'] ?? null,
                'export_provider'         => $meta['export_provider'] ?? null,
                'services'                => $this->discoverModuleServices($name),
                'controllers'             => $this->discoverModuleControllers($name),
                'has_migrations'          => is_dir($dir . 'migrations') && !empty(glob($dir . 'migrations/*.sql')),
            ];
        }

        usort($modules, function ($a, $b) {
            if ($a['core'] !== $b['core']) {
                return $a['core'] ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $modules;
    }

    private function loadModuleDbMapping(string $moduleName): ?array
    {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT mode, database_name, host, port, provisioning_status,
                        last_error_at, provisioned_at, updated_at
                 FROM module_databases WHERE module_name = ? LIMIT 1'
            );
            $stmt->execute([$moduleName]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadModulePermissions(string $moduleName): array
    {
        $file = $this->modulesDir . "/{$moduleName}/permissions.php";
        if (!file_exists($file)) {
            return [];
        }
        try {
            $perms = require $file;
            return is_array($perms) ? $perms : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function discoverModuleServices(string $moduleName): array
    {
        $dir      = $this->modulesDir . "/{$moduleName}/Services/";
        $services = [];
        if (!is_dir($dir)) {
            return $services;
        }
        foreach (glob($dir . '*.php') as $file) {
            $services[] = 'App\\Modules\\' . $moduleName . '\\Services\\' . basename($file, '.php');
        }
        return $services;
    }

    private function discoverModuleControllers(string $moduleName): array
    {
        $dir         = $this->modulesDir . "/{$moduleName}/Controllers/";
        $controllers = [];
        if (!is_dir($dir)) {
            return $controllers;
        }
        foreach (glob($dir . '*.php') as $file) {
            $controllers[] = 'App\\Modules\\' . $moduleName . '\\Controllers\\' . basename($file, '.php');
        }
        return $controllers;
    }

    private function buildDatabase(): array
    {
        $tables = [];

        $pdo = $this->getPdo();
        if (!$pdo) {
            $this->warn('DB non raggiungibile — tabelle non incluse nel contesto.');
            return ['tables' => []];
        }

        try {
            $dbName = $_ENV['DB_NAME'] ?? 'favilla';

            $stmt = $pdo->prepare(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY TABLE_NAME"
            );
            $stmt->execute([$dbName]);
            $tableNames = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tableNames as $tableName) {
                $tables[] = [
                    'name'    => $tableName,
                    'columns' => $this->getTableColumns($pdo, $dbName, $tableName),
                    'indexes' => $this->getTableIndexes($pdo, $dbName, $tableName),
                    'fk'      => $this->getTableForeignKeys($pdo, $dbName, $tableName),
                ];
            }
        } catch (\Throwable $e) {
            $this->warn('Errore lettura schema DB: ' . $e->getMessage());
        }

        return ['tables' => $tables];
    }

    private function getTableColumns(\PDO $pdo, string $dbName, string $tableName): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([$dbName, $tableName]);
        $cols = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $col = [
                'name'     => $row['COLUMN_NAME'],
                'type'     => $row['COLUMN_TYPE'],
                'nullable' => $row['IS_NULLABLE'] === 'YES',
            ];
            if ($row['COLUMN_DEFAULT'] !== null) {
                $col['default'] = $row['COLUMN_DEFAULT'];
            }
            if ($row['EXTRA']) {
                $col['extra'] = $row['EXTRA'];
            }
            if ($row['COLUMN_COMMENT']) {
                $col['comment'] = $row['COLUMN_COMMENT'];
            }
            $cols[] = $col;
        }
        return $cols;
    }

    private function getTableIndexes(\PDO $pdo, string $dbName, string $tableName): array
    {
        $stmt = $pdo->prepare(
            'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns,
                    NON_UNIQUE, INDEX_TYPE
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
             ORDER BY INDEX_NAME'
        );
        $stmt->execute([$dbName, $tableName]);
        $indexes = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $indexes[] = [
                'name'    => $row['INDEX_NAME'],
                'columns' => explode(',', $row['columns']),
                'unique'  => $row['NON_UNIQUE'] === '0',
                'type'    => $row['INDEX_TYPE'],
            ];
        }
        return $indexes;
    }

    private function getTableForeignKeys(\PDO $pdo, string $dbName, string $tableName): array
    {
        $stmt = $pdo->prepare(
            'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                    r.DELETE_RULE, r.UPDATE_RULE
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ?
               AND k.REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $stmt->execute([$dbName, $tableName]);
        $fks = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $fks[] = [
                'constraint'   => $row['CONSTRAINT_NAME'],
                'column'       => $row['COLUMN_NAME'],
                'references'   => $row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME'],
                'on_delete'    => $row['DELETE_RULE'],
                'on_update'    => $row['UPDATE_RULE'],
            ];
        }
        return $fks;
    }

    private function buildRoutes(): array
    {
        $allRoutes = [];

        $coreRoutes = $this->basePath . '/app/Config/routes.php';
        if (file_exists($coreRoutes) && filesize($coreRoutes) > 10) {
            $allRoutes = array_merge($allRoutes, $this->parseRoutesFile($coreRoutes, ''));
        }

        foreach (glob($this->modulesDir . '/*/routes.php') as $routeFile) {
            $moduleName = basename(dirname($routeFile));
            if ($moduleName === '_Template') {
                continue;
            }
            $parsed     = $this->parseRoutesFile($routeFile, $moduleName);
            $allRoutes  = array_merge($allRoutes, $parsed);
        }

        return $allRoutes;
    }

    private function parseRoutesFile(string $file, string $module): array
    {
        $content = file_get_contents($file);
        $routes  = [];

        $groupStack = [];
        $this->parseRouteBlock($content, $groupStack, $routes, $module);

        return $routes;
    }

    private function parseRouteBlock(string $content, array $groupStack, array &$routes, string $module): void
    {
        $offset = 0;
        $len    = strlen($content);

        while ($offset < $len) {
            $groupPos = $this->findPattern($content, '~\$r(?:outer)?->group\s*\(\s*\[~', $offset);

            $routePos = $this->findDirectRoutePos($content, $offset);

            if ($groupPos === false && $routePos === false) {
                break;
            }

            if ($groupPos !== false && ($routePos === false || $groupPos < $routePos)) {
                [$prefix, $middleware, $bodyStart] = $this->extractGroupConfig($content, $groupPos);
                if ($bodyStart === null) {
                    $offset = $groupPos + 1;
                    continue;
                }

                $bodyEnd = $this->findMatchingBrace($content, $bodyStart);
                if ($bodyEnd === false) {
                    $offset = $groupPos + 1;
                    continue;
                }

                $body           = substr($content, $bodyStart + 1, $bodyEnd - $bodyStart - 1);
                $combinedPrefix = rtrim(implode('', array_column($groupStack, 'prefix')) . '/' . ltrim($prefix, '/'), '/');
                $allMiddleware  = array_merge(array_merge(...array_column($groupStack, 'middleware')), $middleware);

                $newStack   = array_merge($groupStack, [['prefix' => '/' . ltrim($prefix, '/'), 'middleware' => $middleware]]);
                $innerStack = [['prefix' => $combinedPrefix ? '/' . ltrim($combinedPrefix, '/') : '', 'middleware' => $allMiddleware]];
                $this->parseRouteBlock($body, $innerStack, $routes, $module);

                $offset = $bodyEnd + 1;
            } else {
                $route = $this->extractDirectRoute($content, $routePos, $groupStack);
                if ($route) {
                    $route['module'] = $module ?: null;
                    $routes[]        = $route;
                }
                $offset = $routePos + 1;
            }
        }
    }

    private function findPattern(string $content, string $pattern, int $offset): int|false
    {
        $sub = substr($content, $offset);
        if (preg_match($pattern, $sub, $m, PREG_OFFSET_CAPTURE)) {
            return $offset + $m[0][1];
        }
        return false;
    }

    private function findDirectRoutePos(string $content, int $offset): int|false
    {
        $sub = substr($content, $offset);
        if (preg_match('~\$r(?:outer)?->(get|post|put|patch|delete)\s*\(~i', $sub, $m, PREG_OFFSET_CAPTURE)) {
            return $offset + $m[0][1];
        }
        return false;
    }

    private function extractGroupConfig(string $content, int $pos): array
    {
        $bracketStart = strpos($content, '[', $pos);
        if ($bracketStart === false) {
            return ['', [], null];
        }

        $bracketEnd = $this->findMatchingBracket($content, $bracketStart);
        if ($bracketEnd === false) {
            return ['', [], null];
        }

        $configStr = substr($content, $bracketStart, $bracketEnd - $bracketStart + 1);

        $prefix = '';
        if (preg_match("/'prefix'\s*=>\s*'([^']+)'/", $configStr, $m)) {
            $prefix = $m[1];
        }

        $middleware = [];
        if (preg_match_all('/([A-Za-z]+Middleware)::(?:class|withPermission\(\'([^\']+)\'\))/', $configStr, $mm)) {
            foreach ($mm[1] as $i => $mwClass) {
                if ($mm[2][$i]) {
                    $middleware[] = $mwClass . ':' . $mm[2][$i];
                } else {
                    $middleware[] = $mwClass;
                }
            }
        }

        $funcPos   = strpos($content, 'function', $bracketEnd);
        $bodyStart = $funcPos !== false ? strpos($content, '{', $funcPos) : null;

        return [$prefix, $middleware, $bodyStart];
    }

    private function extractDirectRoute(string $content, int $pos, array $groupStack): ?array
    {
        $sub = substr($content, $pos, 300);
        if (!preg_match(
            '~\$r(?:outer)?->(get|post|put|patch|delete)\s*\(\s*\'([^\']+)\'\s*,\s*\[([A-Za-z\\\\]+)::class\s*,\s*\'([^\']+)\'\s*\](?:[^)]*)\)\s*(?:->\s*name\s*\(\s*\'([^\']+)\'\s*\))?~i',
            $sub,
            $m
        )) {
            return null;
        }

        $method     = strtoupper($m[1]);
        $path       = $m[2];
        $controller = $m[3];
        $action     = $m[4];
        $name       = $m[5] ?? null;

        $prefix = '';
        foreach ($groupStack as $g) {
            $prefix .= $g['prefix'] ?? '';
        }

        $fullPath = '/' . ltrim($prefix . $path, '/');
        $fullPath = preg_replace('~//+~', '/', $fullPath);

        $middleware = [];
        foreach ($groupStack as $g) {
            foreach ($g['middleware'] ?? [] as $mw) {
                if (!in_array($mw, $middleware, true)) {
                    $middleware[] = $mw;
                }
            }
        }

        $controllerShort = class_exists($controller)
            ? $controller
            : (preg_match('/\\\\([^\\\\]+)$/', $controller, $cm) ? $cm[1] : $controller);

        return [
            'method'     => $method,
            'uri'        => $fullPath,
            'name'       => $name,
            'controller' => $controllerShort . '@' . $action,
            'middleware' => $middleware,
        ];
    }

    private function findMatchingBrace(string $content, int $openPos): int|false
    {
        $depth = 0;
        $len   = strlen($content);
        for ($i = $openPos; $i < $len; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false;
    }

    private function findMatchingBracket(string $content, int $openPos): int|false
    {
        $depth = 0;
        $len   = strlen($content);
        for ($i = $openPos; $i < $len; $i++) {
            if ($content[$i] === '[') {
                $depth++;
            } elseif ($content[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false;
    }

    private function buildPermissions(): array
    {
        $all = [];

        // Try DB first (authoritative)
        $pdo = $this->getPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query('SELECT slug, name, module FROM permissions ORDER BY module, slug');
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $all[] = [
                        'slug'   => $row['slug'],
                        'name'   => $row['name'],
                        'module' => $row['module'],
                    ];
                }
                return $all;
            } catch (\Throwable $e) {
                // fallback to filesystem
            }
        }

        foreach (glob($this->modulesDir . '/*/permissions.php') as $file) {
            $moduleName = basename(dirname($file));
            if ($moduleName === '_Template') {
                continue;
            }
            try {
                $perms = require $file;
                if (is_array($perms)) {
                    foreach ($perms as $p) {
                        $all[] = [
                            'slug'   => $p['slug'],
                            'name'   => $p['name'] ?? $p['slug'],
                            'module' => strtolower($moduleName),
                        ];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return $all;
    }

    private function buildServices(): array
    {
        $services = [];

        foreach (glob($this->basePath . '/app/Services/*.php') as $file) {
            $services[] = [
                'class'  => 'App\\Services\\' . basename($file, '.php'),
                'module' => 'core',
            ];
        }

        foreach (glob($this->modulesDir . '/*/Services/*.php') as $file) {
            $parts     = explode('/', str_replace('\\', '/', $file));
            $className = basename($file, '.php');
            $moduleIdx = array_search('Modules', $parts);
            $module    = $moduleIdx !== false ? ($parts[$moduleIdx + 1] ?? 'unknown') : 'unknown';

            $services[] = [
                'class'  => "App\\Modules\\{$module}\\Services\\{$className}",
                'module' => $module,
            ];
        }

        return $services;
    }


    private function buildAssets(): array
    {
        $cssDir = $this->basePath . '/public/assets/css/';
        $jsDir  = $this->basePath . '/public/assets/js/';

        $css = [];
        foreach (glob($cssDir . '*.css') as $file) {
            $css[] = basename($file);
        }

        $js = [];
        foreach (glob($jsDir . '*.js') as $file) {
            $js[] = basename($file);
        }

        sort($css);
        sort($js);

        return ['css' => $css, 'js' => $js];
    }


    private function buildConfig(): array
    {
        $this->loadEnv();

        return [
            'app' => [
                'name'         => $_ENV['APP_NAME'] ?? 'Favilla',
                'env'          => $_ENV['APP_ENV'] ?? 'production',
                'url'          => $_ENV['APP_URL'] ?? '',
                'base_path'    => $_ENV['APP_BASE_PATH'] ?? '',
                'maintenance'  => $_ENV['MAINTENANCE_MODE'] ?? 'false',
                'session_lifetime' => $_ENV['SESSION_LIFETIME'] ?? '480',
            ],
            'database' => [
                'host'     => $_ENV['DB_HOST'] ?? 'localhost',
                'port'     => $_ENV['DB_PORT'] ?? '3306',
                'name'     => $_ENV['DB_NAME'] ?? 'favilla',
                'charset'  => 'utf8mb4',
            ],
        ];
    }

    private function loadEnv(): void
    {
        $envFile = $this->basePath . '/.env';
        if (!file_exists($envFile)) {
            return;
        }
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\"'");
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
            }
        }
    }


    private function getPdo(): ?\PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $this->loadEnv();

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST']     ?? 'localhost',
                $_ENV['DB_PORT']     ?? '3306',
                $_ENV['DB_NAME'] ?? 'favilla'
            );
            $this->pdo = new \PDO(
                $dsn,
                $_ENV['DB_USERNAME'] ?? 'root',
                $_ENV['DB_PASSWORD'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            return $this->pdo;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function success(string $msg): void
    {
        echo "\033[32m[OK]\033[0m {$msg}\n";
    }
    private function error(string $msg): void
    {
        echo "\033[31m[ERR]\033[0m {$msg}\n";
        exit(1);
    }
    private function warn(string $msg): void
    {
        echo "\033[33m[WARN]\033[0m {$msg}\n";
    }
    private function info(string $msg): void
    {
        echo "\033[36m[INFO]\033[0m {$msg}\n";
    }
}
