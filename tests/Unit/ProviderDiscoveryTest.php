<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SearchableModule;
use App\Services\ProviderDiscovery;
use Tests\ModuleTestCase;

class ProviderDiscoveryTest extends ModuleTestCase
{
    public function testInstantiateLogsMissingClass(): void
    {
        $discovery = new TestableProviderDiscovery();

        $result = $discovery->instantiateForTest('Tests\\Unit\\MissingProviderClass', [SearchableModule::class]);

        $this->assertNull($result);
        $this->assertCount(1, $discovery->messages);
        $this->assertStringContainsString('not found', $discovery->messages[0]);
    }

    public function testInstantiateLogsContractViolation(): void
    {
        $discovery = new TestableProviderDiscovery();

        $result = $discovery->instantiateForTest(NotAProvider::class, [SearchableModule::class]);

        $this->assertNull($result);
        $this->assertCount(1, $discovery->messages);
        $this->assertStringContainsString('does not implement', $discovery->messages[0]);
    }

    public function testInstantiateAcceptsAnyMatchingContract(): void
    {
        $discovery = new TestableProviderDiscovery();

        $result = $discovery->instantiateForTest(ValidDiscoveryProvider::class, [
            \Countable::class,            // non implementata
            SearchableModule::class,      // implementata
        ]);

        $this->assertInstanceOf(ValidDiscoveryProvider::class, $result);
        $this->assertCount(0, $discovery->messages);
    }

    public function testDiscoverFlatFlattensGroupedProviders(): void
    {
        $discovery = new class () extends ProviderDiscovery {
            public function discover(string $jsonField, string $fileSuffix, array $contracts, array $excludeModules = []): array
            {
                return [
                    'ModuleA' => ['a1', 'a2'],
                    'ModuleB' => ['b1'],
                ];
            }
        };

        $this->assertSame(
            ['a1', 'a2', 'b1'],
            $discovery->discoverFlat('x', 'Y', [SearchableModule::class])
        );
    }
}

class TestableProviderDiscovery extends ProviderDiscovery
{
    /** @var string[] */
    public array $messages = [];

    public function instantiateForTest(string $className, array $contracts): ?object
    {
        return $this->instantiate($className, $contracts);
    }

    protected function reportFailure(string $message): void
    {
        $this->messages[] = $message;
    }
}

class NotAProvider
{
}

class ValidDiscoveryProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        return [];
    }

    public function getSearchLabel(): string
    {
        return 'Test';
    }

    public function getSearchIcon(): string
    {
        return 'fa-vial';
    }
}
