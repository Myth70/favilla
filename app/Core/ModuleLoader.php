<?php

declare(strict_types=1);

namespace App\Core;

class ModuleLoader
{
    private string $basePath;
    private array $modules = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    /**
     * Load module configuration from modules.php, then auto-discover
     * any additional modules that have a module.json but are not
     * registered in modules.php (e.g. imported modules).
     */
    public function loadConfig(): void
    {
        $configFile = $this->basePath . '/app/Config/modules.php';
        if (file_exists($configFile)) {
            $this->modules = require $configFile;
        }

        $this->discoverModules();
    }

    /**
     * Scan module.json files in app/Modules subdirectories for modules
     * not already in modules.php. Discovered modules are inserted
     * before the Admin block (which must stay last).
     */
    private function discoverModules(): void
    {
        $registered = array_column($this->modules, 'name');
        $modulesPath = $this->basePath . '/app/Modules';
        $pattern = $modulesPath . '/*/module.json';
        $jsonFiles = glob($pattern) ?: [];

        foreach ($jsonFiles as $jsonFile) {
            $dirName = basename(dirname($jsonFile));

            if ($dirName === '_Template' || in_array($dirName, $registered, true)) {
                continue;
            }

            $raw = file_get_contents($jsonFile);
            if ($raw === false) {
                continue;
            }
            $meta = json_decode($raw, true);
            if (!is_array($meta) || ($meta['name'] ?? '') !== $dirName) {
                continue;
            }

            $entry = [
                'name'    => $dirName,
                'enabled' => true,
                'menu'    => $meta['menu'] ?? [],
            ];

            // Insert before the last element if it's Admin (Admin must stay last)
            $lastIndex = count($this->modules) - 1;
            if ($lastIndex >= 0 && ($this->modules[$lastIndex]['name'] ?? '') === 'Admin') {
                array_splice($this->modules, $lastIndex, 0, [$entry]);
            } else {
                $this->modules[] = $entry;
            }
        }
    }

    /**
     * Load all enabled module routes into the given Router.
     */
    public function loadRoutes(Router $router): void
    {
        foreach ($this->modules as $module) {
            if (!($module['enabled'] ?? true)) {
                continue;
            }

            $routeFile = $this->basePath . '/app/Modules/' . $module['name'] . '/routes.php';
            if (file_exists($routeFile)) {
                require $routeFile;
            }
        }
    }

    /**
     * Get all loaded module configs.
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Applica gli override di stato dal DB sulla configurazione caricata da modules.php.
     * Tabella vuota = comportamento invariato.
     * Da chiamare in Application::handleRequest() dopo loadConfig().
     *
     * Il risultato viene memorizzato in sessione per evitare una query per ogni request.
     * La cache si invalida automaticamente quando un admin aggiorna uno stato di modulo
     * chiamando invalidateDbOverridesCache().
     */
    public function loadDbOverrides(\PDO $pdo): void
    {
        // Cache di sessione con TTL 60s: l'invalidazione esplicita copre solo
        // la sessione dell'admin che modifica lo stato; il TTL garantisce che
        // anche le ALTRE sessioni attive recepiscano il cambio entro un minuto.
        $cachedAt = (int) ($_SESSION['_module_states_cached_at'] ?? 0);
        if (PHP_SAPI !== 'cli'
            && isset($_SESSION['_module_states_cache'])
            && (time() - $cachedAt) < 60
        ) {
            $overrides = $_SESSION['_module_states_cache'];
            $this->applyOverrides($overrides);
            return;
        }

        try {
            $stmt = $pdo->query('SELECT name, enabled, testing FROM module_states');
        } catch (\PDOException $e) {
            // Tabella non ancora migrata: degradazione silenziosa
            return;
        }

        $overrides = [];
        foreach ($stmt->fetchAll() as $row) {
            $overrides[$row['name']] = $row;
        }

        if (PHP_SAPI !== 'cli') {
            $_SESSION['_module_states_cache'] = $overrides;
            $_SESSION['_module_states_cached_at'] = time();
        }

        $this->applyOverrides($overrides);
    }

    /**
     * Invalida la cache di sessione degli stati modulo.
     * Da chiamare in ModuleController dopo ogni salvataggio di stato.
     */
    public static function invalidateDbOverridesCache(): void
    {
        unset($_SESSION['_module_states_cache'], $_SESSION['_module_states_cached_at']);
    }

    private function applyOverrides(array $overrides): void
    {
        foreach ($this->modules as &$module) {
            $name = $module['name'];
            if (isset($overrides[$name])) {
                $module['enabled'] = (bool) $overrides[$name]['enabled'];
                $module['testing'] = (bool) $overrides[$name]['testing'];
            }
        }
        unset($module);
    }

    /**
     * Legge i permessi dichiarati da un modulo specifico.
     * Ritorna array vuoto se permissions.php non esiste — mai eccezione.
     */
    public function scanPermissions(string $moduleName): array
    {
        $file = $this->basePath . '/app/Modules/' . $moduleName . '/permissions.php';
        if (!file_exists($file)) {
            return [];
        }
        $perms = require $file;
        return is_array($perms) ? $perms : [];
    }

    /**
     * Read and parse module.json from a module directory.
     * Returns null if file does not exist or is invalid — never throws.
     */
    public function readModuleJson(string $moduleName): ?array
    {
        $file = $this->basePath . '/app/Modules/' . $moduleName . '/module.json';
        if (!file_exists($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Scansiona app/Modules/ e ritorna tutti i moduli trovati su filesystem
     * con il loro stato corrente e i permessi dichiarati vs importati nel DB.
     * Usato da ModuleController::index().
     */
    public function getAllModulesWithStatus(\PDO $pdo): array
    {
        $modulesPath = $this->basePath . '/app/Modules/';
        $dirs = glob($modulesPath . '*', GLOB_ONLYDIR);

        $importedSlugs = array_flip(
            $pdo->query('SELECT slug FROM permissions')->fetchAll(\PDO::FETCH_COLUMN)
        );

        $configByName = [];
        foreach ($this->modules as $m) {
            $configByName[$m['name']] = $m;
        }

        $result = [];
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if ($name === '_Template') {
                continue;
            }

            // Core modules are always active and hidden from Admin management
            if (($configByName[$name]['core'] ?? false) === true) {
                continue;
            }

            $declared   = $this->scanPermissions($name);
            $permStatus = array_map(fn ($p) => [
                'slug'     => $p['slug'],
                'name'     => $p['name'],
                'imported' => isset($importedSlugs[$p['slug']]),
            ], $declared);

            $config = $configByName[$name] ?? ['enabled' => false, 'testing' => false];
            $moduleJson = $this->readModuleJson($name);
            $hasModuleJson = $moduleJson !== null;
            $inConfig = isset($configByName[$name]);

            $result[] = [
                'name'            => $name,
                'enabled'         => $config['enabled'] ?? false,
                'testing'         => $config['testing'] ?? false,
                'in_config'       => $inConfig,
                'auto_discovered' => !$inConfig && $hasModuleJson,
                'has_module_json' => $hasModuleJson,
                'version'         => $moduleJson['version'] ?? null,
                'description'     => $moduleJson['description'] ?? null,
                'database_mode'   => $moduleJson['database'] ?? 'shared',
                'permissions'     => $permStatus,
                'new_count'       => count(array_filter($permStatus, fn ($p) => !$p['imported'])),
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $result;
    }
}
