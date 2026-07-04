<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Contracts\ExportableModule;

/**
 * Discovers and manages ExportableModule providers across all enabled modules.
 */
class ExportProviderService
{
    /** @var array<string, ExportableModule> Cached provider instances keyed by module name */
    private array $providers = [];
    private bool $discovered = false;

    /**
     * Discover export providers from all enabled modules.
     *
     * Discovery order:
     *  1. Check module.json for "export_provider" FQCN
     *  2. Auto-discover Providers/*ExportProvider.php files
     *
     * Skips Reports module itself and _Template.
     */
    public function discoverProviders(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        $byModule = app(\App\Services\ProviderDiscovery::class)->discover(
            'export_provider',
            'ExportProvider',
            [ExportableModule::class],
            ['Reports'] // skip self
        );

        // Un solo provider per modulo (il primo valido).
        foreach ($byModule as $moduleName => $instances) {
            $this->providers[$moduleName] = $instances[0];
        }
    }

    /**
     * Get all discovered export providers.
     *
     * @return array<string, ExportableModule> Keyed by module name
     */
    public function getProviders(): array
    {
        $this->discoverProviders();
        return $this->providers;
    }

    /**
     * Get a specific provider by module name.
     */
    public function getProviderByModule(string $moduleName): ?ExportableModule
    {
        $this->discoverProviders();
        return $this->providers[$moduleName] ?? null;
    }

    /**
     * Check if a module/source pair exists in discovered providers.
     */
    public function sourceExists(string $module, string $sourceKey): bool
    {
        $provider = $this->getProviderByModule($module);
        if ($provider === null) {
            return false;
        }

        foreach ($provider->getDataSources() as $source) {
            if (($source['key'] ?? null) === $sourceKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all data sources visible to the current user, grouped by module.
     *
     * Accepts either a user array (from auth()) or explicit permission params.
     *
     * @param array $user User array from auth() with 'permissions' and 'roles' keys
     * @return array Array of modules with their data sources
     */
    public function getSourcesForUser(array $user): array
    {
        $this->discoverProviders();

        $permissions = $user['permissions'] ?? [];
        $isAdmin = in_array('admin', $user['roles'] ?? [], true);
        $result = [];

        foreach ($this->providers as $moduleName => $provider) {
            $sources = $provider->getDataSources();
            $visibleSources = [];

            foreach ($sources as $source) {
                $requiredPerm = $source['permission'] ?? null;

                if ($isAdmin || $requiredPerm === null || in_array($requiredPerm, $permissions, true)) {
                    $source['module'] = $moduleName;
                    $source['module_icon'] = $provider->getExportModuleIcon();
                    $visibleSources[] = $source;
                }
            }

            if (!empty($visibleSources)) {
                $result[] = [
                    'module'  => $moduleName,
                    'label'   => $provider->getExportModuleName(),
                    'icon'    => $provider->getExportModuleIcon(),
                    'sources' => $visibleSources,
                ];
            }
        }

        return $result;
    }

    /**
     * Get field definitions for a specific data source.
     *
     * Permission check uses the current authenticated user.
     *
     * @return array|null Field definitions or null if source not found / not permitted
     */
    public function getSourceFields(string $module, string $sourceKey): ?array
    {
        $provider = $this->getProviderByModule($module);
        if ($provider === null) {
            return null;
        }

        $user = auth();
        $permissions = $user['permissions'] ?? [];
        $isAdmin = in_array('admin', $user['roles'] ?? [], true);

        $sources = $provider->getDataSources();
        foreach ($sources as $source) {
            if ($source['key'] === $sourceKey) {
                $requiredPerm = $source['permission'] ?? null;
                if ($isAdmin || $requiredPerm === null || in_array($requiredPerm, $permissions, true)) {
                    return $source['fields'] ?? [];
                }
                return null;
            }
        }

        return null;
    }

    /**
     * Fetch data from a provider's data source.
     *
     * Permission check uses the current authenticated user.
     * Sort column is validated against declared sortable fields.
     * Limit is capped at 10000.
     *
     * @return array Data rows
     * @throws \RuntimeException If source not found or not permitted
     */
    public function fetchData(
        string $module,
        string $sourceKey,
        array  $filters = [],
        string $sort = 'created_at',
        string $dir = 'DESC',
        int    $limit = 10000
    ): array {
        $provider = $this->getProviderByModule($module);
        if ($provider === null) {
            throw new \RuntimeException("Modulo '{$module}' non trovato o non abilitato.");
        }

        // Verify permission on the source
        $user = auth();
        $permissions = $user['permissions'] ?? [];
        $isAdmin = in_array('admin', $user['roles'] ?? [], true);

        $sources = $provider->getDataSources();
        $allowed = false;
        $allowedSorts = [];

        foreach ($sources as $source) {
            if ($source['key'] === $sourceKey) {
                $requiredPerm = $source['permission'] ?? null;
                if ($isAdmin || $requiredPerm === null || in_array($requiredPerm, $permissions, true)) {
                    $allowed = true;
                }
                // Build sort whitelist from sortable fields
                foreach ($source['fields'] ?? [] as $field) {
                    if (!empty($field['sortable'])) {
                        $allowedSorts[] = $field['name'];
                    }
                }
                break;
            }
        }

        if (!$allowed) {
            throw new \RuntimeException("Accesso negato alla sorgente '{$sourceKey}' del modulo '{$module}'.");
        }

        // Validate sort column against whitelist
        if (!empty($allowedSorts) && !in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        // Validate sort direction
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        // Cap limit
        $limit = min($limit, 10000);

        return $provider->getExportData($sourceKey, $filters, $sort, $dir, $limit);
    }

    /**
     * Fetch a single record from a provider for document generation.
     *
     * @return array|null Record data or null if not found
     */
    public function fetchSingleRecord(string $module, string $sourceKey, int $recordId): ?array
    {
        $provider = $this->getProviderByModule($module);
        if ($provider === null) {
            return null;
        }

        return $provider->getSingleRecord($sourceKey, $recordId);
    }

    /**
     * Instantiate a class by FQCN if it implements ExportableModule.
     */
}
