<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * Coerenza minima tra configurazione moduli e struttura del progetto.
 */
class ModulesCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'modules';
    }

    public function label(): string
    {
        return 'Moduli';
    }

    public function description(): string
    {
        return 'Coerenza minima tra configurazione moduli e struttura del progetto.';
    }

    protected function checks(): array
    {
        $checks = [];
        $base = BASE_PATH;

        $configuredModules = [];
        $modulesConfigPath = $base . '/app/Config/modules.php';
        if (file_exists($modulesConfigPath)) {
            $config = require $modulesConfigPath;
            if (is_array($config)) {
                foreach ($config as $entry) {
                    if (is_array($entry) && !empty($entry['name'])) {
                        $configuredModules[] = (string) $entry['name'];
                    }
                }
            }
        }

        if (empty($configuredModules)) {
            $checks[] = $this->warn('Configurazione moduli', 'Impossibile leggere l elenco moduli configurati');
            return $checks;
        }

        $missingDirectories = [];
        $missingDescriptors = [];
        $missingRoutes      = [];
        $missingPermissions = [];

        foreach ($configuredModules as $moduleName) {
            $modulePath = $base . '/app/Modules/' . $moduleName;
            if (!is_dir($modulePath)) {
                $missingDirectories[] = $moduleName;
                continue;
            }
            if (!file_exists($modulePath . '/module.json')) {
                $missingDescriptors[] = $moduleName;
            }
            if (!file_exists($modulePath . '/routes.php')) {
                $missingRoutes[] = $moduleName;
            }
            if (!file_exists($modulePath . '/permissions.php')) {
                $missingPermissions[] = $moduleName;
            }
        }

        $checks[] = empty($missingDirectories)
            ? $this->ok('Cartelle moduli', count($configuredModules) . ' moduli trovati')
            : $this->fail('Cartelle moduli', 'Assenti: ' . implode(', ', array_slice($missingDirectories, 0, 5)) . $this->ellipsis($missingDirectories));

        $checks[] = empty($missingDescriptors)
            ? $this->ok('Descriptori module.json', 'presenti dove attesi')
            : $this->warn('Descrittori modulo', 'module.json assente in: ' . implode(', ', array_slice($missingDescriptors, 0, 5)) . $this->ellipsis($missingDescriptors));

        $checks[] = empty($missingRoutes)
            ? $this->ok('Definizione route modulo', 'nessuna anomalia rilevata')
            : $this->warn('Route modulo', 'routes.php assente in: ' . implode(', ', array_slice($missingRoutes, 0, 5)) . $this->ellipsis($missingRoutes));

        $checks[] = empty($missingPermissions)
            ? $this->ok('Definizione permessi modulo', 'nessuna anomalia rilevata')
            : $this->warn('Permessi modulo', 'permissions.php assente in: ' . implode(', ', array_slice($missingPermissions, 0, 5)) . $this->ellipsis($missingPermissions));

        $lastConfigured = end($configuredModules);
        $checks[] = $lastConfigured === 'Admin'
            ? $this->ok('Ordine moduli', 'Admin configurato correttamente in coda')
            : $this->fail('Ordine moduli', 'Admin deve restare ultimo in app/Config/modules.php');

        return $checks;
    }

    /**
     * @param array<int,string> $items
     */
    private function ellipsis(array $items): string
    {
        return count($items) > 5 ? '...' : '';
    }
}
