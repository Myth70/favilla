<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services;

use App\Contracts\ContactSourceProvider;

/**
 * Discovers and manages ContactSourceProvider instances across all enabled modules.
 *
 * Mirrors the discovery pattern of ExportProviderService (Reports module):
 *  1. Reads module.json["contact_source_provider"] (FQCN) when present.
 *  2. Falls back to glob app/Modules/{Module}/Providers/ *ContactSourceProvider.php.
 *
 * Permission filtering is applied per-source against the current user.
 */
class ContactSourceService
{
    /** @var array<string, ContactSourceProvider> Cached providers keyed by module name */
    private array $providers = [];
    private bool $discovered = false;

    public function discoverProviders(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        $byModule = app(\App\Services\ProviderDiscovery::class)->discover(
            'contact_source_provider',
            'ContactSourceProvider',
            [ContactSourceProvider::class],
            ['Contatti'] // skip self
        );

        // Un solo provider per modulo (il primo valido).
        foreach ($byModule as $moduleName => $instances) {
            $this->providers[$moduleName] = $instances[0];
        }
    }

    /** @return array<string, ContactSourceProvider> */
    public function getProviders(): array
    {
        $this->discoverProviders();
        return $this->providers;
    }

    public function getProviderByModule(string $moduleName): ?ContactSourceProvider
    {
        $this->discoverProviders();
        return $this->providers[$moduleName] ?? null;
    }

    /**
     * Sources visible to the user, grouped by module.
     *
     * @return array<int, array{module:string,label:string,icon:string,sources:array<int,array>}>
     */
    public function getSourcesForUser(array $user): array
    {
        $this->discoverProviders();

        $permissions = $user['permissions'] ?? [];
        $isAdmin = in_array('admin', $user['roles'] ?? [], true);
        $result = [];

        foreach ($this->providers as $moduleName => $provider) {
            $visibleSources = [];

            foreach ($provider->getContactSources() as $source) {
                $requiredPerm = $source['permission'] ?? null;
                if ($isAdmin || $requiredPerm === null || in_array($requiredPerm, $permissions, true)) {
                    $source['module'] = $moduleName;
                    $source['module_icon'] = $provider->getContactModuleIcon();
                    $visibleSources[] = $source;
                }
            }

            if (!empty($visibleSources)) {
                $result[] = [
                    'module'  => $moduleName,
                    'label'   => $provider->getContactModuleName(),
                    'icon'    => $provider->getContactModuleIcon(),
                    'sources' => $visibleSources,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{rows: array<int,array>, total: int}
     * @throws \RuntimeException
     */
    public function fetchList(
        string $module,
        string $sourceKey,
        array $filters = [],
        int $page = 1,
        int $perPage = 25
    ): array {
        $provider = $this->requirePermittedProvider($module, $sourceKey);
        return $provider->listContacts($sourceKey, $filters, $page, $perPage);
    }

    /** @throws \RuntimeException */
    public function fetchOne(string $module, string $sourceKey, int $sourceId): ?array
    {
        $provider = $this->requirePermittedProvider($module, $sourceKey);
        return $provider->getContact($sourceKey, $sourceId);
    }

    /**
     * Find a single source descriptor by (module, key) for UI rendering.
     */
    public function findSource(string $module, string $sourceKey): ?array
    {
        $provider = $this->getProviderByModule($module);
        if ($provider === null) {
            return null;
        }
        foreach ($provider->getContactSources() as $source) {
            if (($source['key'] ?? null) === $sourceKey) {
                $source['module'] = $module;
                $source['module_label'] = $provider->getContactModuleName();
                $source['module_icon'] = $provider->getContactModuleIcon();
                return $source;
            }
        }
        return null;
    }

    private function requirePermittedProvider(string $module, string $sourceKey): ContactSourceProvider
    {
        $provider = $this->getProviderByModule($module);
        if ($provider === null) {
            throw new \RuntimeException("Modulo '{$module}' non trovato o non abilitato.");
        }

        $user = auth() ?? [];
        $permissions = $user['permissions'] ?? [];
        $isAdmin = in_array('admin', $user['roles'] ?? [], true);

        foreach ($provider->getContactSources() as $source) {
            if (($source['key'] ?? null) === $sourceKey) {
                $requiredPerm = $source['permission'] ?? null;
                if ($isAdmin || $requiredPerm === null || in_array($requiredPerm, $permissions, true)) {
                    return $provider;
                }
                throw new \RuntimeException("Accesso negato alla sorgente '{$sourceKey}' del modulo '{$module}'.");
            }
        }

        throw new \RuntimeException("Sorgente '{$sourceKey}' non trovata nel modulo '{$module}'.");
    }

}
