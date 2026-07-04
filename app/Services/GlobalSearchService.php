<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SearchableModule;

/**
 * Discovers and queries all registered search providers.
 *
 * Providers are discovered from:
 *  1. module.json → "search_provider" field (FQCN)
 *  2. Auto-discovery: Providers/*SearchProvider.php in each enabled module directory
 */
class GlobalSearchService
{
    /** @var SearchableModule[]|null */
    private ?array $providers = null;

    /**
     * Get all discovered search providers.
     *
     * @return SearchableModule[]
     */
    public function getProviders(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $this->providers = app(ProviderDiscovery::class)
            ->discoverFlat('search_provider', 'SearchProvider', [SearchableModule::class]);

        return $this->providers;
    }

    /**
     * Run a search across all providers.
     *
     * @return array<string,array{label:string,icon:string,results:array}>
     */
    public function search(string $query, int $userId, int $perModule = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $grouped = [];
        foreach ($this->getProviders() as $provider) {
            try {
                $results = $provider->search($query, $userId, $perModule);
                if (!empty($results)) {
                    $grouped[$provider->getSearchLabel()] = [
                        'label'   => $provider->getSearchLabel(),
                        'icon'    => $provider->getSearchIcon(),
                        'results' => $results,
                    ];
                }
            } catch (\Throwable $e) {
                $this->reportFailure('Provider ' . get_class($provider) . ' search failed: ' . $e->getMessage());
            }
        }

        return $grouped;
    }

    protected function reportFailure(string $message): void
    {
        app_log('error', '[GlobalSearchService] ' . $message);
    }
}
