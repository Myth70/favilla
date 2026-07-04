<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Discovery unificata dei provider di modulo (search, dashboard, export,
 * contact source, ...). Sostituisce i quattro loop fotocopia che vivevano
 * nei singoli service consumer.
 *
 * Strategia per ogni modulo abilitato (esclusi _Template e $excludeModules):
 *  1. module.json → campo $jsonField (FQCN): se valido, è l'unico provider
 *     del modulo; se non istanziabile si passa al fallback.
 *  2. Fallback: glob Providers/*{$fileSuffix}.php.
 * Le classi mancanti o non conformi ai contratti vengono loggate e ignorate
 * (un provider rotto non deve abbattere il consumer).
 */
class ProviderDiscovery
{
    /**
     * @param string   $jsonField      Campo FQCN in module.json (es. 'search_provider')
     * @param string   $fileSuffix     Suffisso dei file in Providers/ (es. 'SearchProvider')
     * @param string[] $contracts      Interfacce accettate (basta che una combaci)
     * @param string[] $excludeModules Moduli da saltare oltre a _Template (es. il modulo consumer)
     * @return array<string, object[]> Istanze valide raggruppate per nome modulo
     */
    public function discover(string $jsonField, string $fileSuffix, array $contracts, array $excludeModules = []): array
    {
        $byModule   = [];
        $modulesDir = dirname(__DIR__) . '/Modules';
        if (!is_dir($modulesDir)) {
            return $byModule;
        }

        foreach (scandir($modulesDir) as $dir) {
            if ($dir[0] === '.' || $dir === '_Template' || in_array($dir, $excludeModules, true)) {
                continue;
            }
            $path = $modulesDir . '/' . $dir;
            if (!is_dir($path) || !isModuleEnabled($dir)) {
                continue;
            }

            // 1. FQCN esplicito in module.json
            $jsonPath = $path . '/module.json';
            if (is_file($jsonPath)) {
                $meta = json_decode((string) @file_get_contents($jsonPath), true);
                $fqcn = is_array($meta) ? ($meta[$jsonField] ?? null) : null;
                if (is_string($fqcn) && $fqcn !== '') {
                    $provider = $this->instantiate($fqcn, $contracts);
                    if ($provider !== null) {
                        $byModule[$dir] = [$provider];
                        continue;
                    }
                }
            }

            // 2. Auto-discovery Providers/*{suffix}.php
            $providerDir = $path . '/Providers';
            if (!is_dir($providerDir)) {
                continue;
            }
            foreach (glob($providerDir . '/*' . $fileSuffix . '.php') ?: [] as $file) {
                $className = 'App\\Modules\\' . $dir . '\\Providers\\' . basename($file, '.php');
                $provider  = $this->instantiate($className, $contracts);
                if ($provider !== null) {
                    $byModule[$dir][] = $provider;
                }
            }
        }

        return $byModule;
    }

    /**
     * Variante comoda: lista piatta di provider (ordinata per modulo).
     *
     * @param string[] $contracts
     * @param string[] $excludeModules
     * @return object[]
     */
    public function discoverFlat(string $jsonField, string $fileSuffix, array $contracts, array $excludeModules = []): array
    {
        $byModule = $this->discover($jsonField, $fileSuffix, $contracts, $excludeModules);
        return $byModule === [] ? [] : array_merge(...array_values($byModule));
    }

    /**
     * Istanzia e valida un provider contro i contratti accettati.
     *
     * @param string[] $contracts
     */
    protected function instantiate(string $className, array $contracts): ?object
    {
        if (!class_exists($className)) {
            $this->reportFailure('Provider class not found: ' . $className);
            return null;
        }

        try {
            $instance = app($className);
        } catch (\Throwable $e) {
            $this->reportFailure('Provider instantiation failed for ' . $className . ': ' . $e->getMessage());
            return null;
        }

        foreach ($contracts as $contract) {
            if ($instance instanceof $contract) {
                return $instance;
            }
        }

        $this->reportFailure('Provider does not implement ' . implode('|', $contracts) . ': ' . $className);
        return null;
    }

    protected function reportFailure(string $message): void
    {
        app_log('error', '[ProviderDiscovery] ' . $message);
    }
}
