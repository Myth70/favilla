<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SearchableModule;
use App\Services\GlobalSearchService;
use Tests\ModuleTestCase;

class GlobalSearchServiceTest extends ModuleTestCase
{
    public function testSearchReturnsResultsAndLogsFailingProviders(): void
    {
        $service = new TestableGlobalSearchService([
            new SuccessfulSearchProvider(),
            new FailingSearchProvider(),
        ]);

        $results = $service->search('report', 7, 3);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Report', $results);
        $this->assertCount(1, $results['Report']['results']);
        $this->assertCount(1, $service->messages);
        $this->assertStringContainsString('FailingSearchProvider', $service->messages[0]);
    }

}

class TestableGlobalSearchService extends GlobalSearchService
{
    /** @var SearchableModule[] */
    private array $providers;
    /** @var string[] */
    public array $messages = [];

    /** @param SearchableModule[] $providers */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    protected function reportFailure(string $message): void
    {
        $this->messages[] = $message;
    }
}

class SuccessfulSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        return [[
            'title' => 'Report ' . $query,
            'subtitle' => 'Risultato di test',
            'url' => '/reports/1',
            'icon' => 'fa-file-lines',
            'badge' => null,
        ]];
    }

    public function getSearchLabel(): string
    {
        return 'Report';
    }

    public function getSearchIcon(): string
    {
        return 'fa-file-lines';
    }
}

class FailingSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        throw new \RuntimeException('provider offline');
    }

    public function getSearchLabel(): string
    {
        return 'Broken';
    }

    public function getSearchIcon(): string
    {
        return 'fa-bug';
    }
}

class InvalidSearchProvider
{
}
