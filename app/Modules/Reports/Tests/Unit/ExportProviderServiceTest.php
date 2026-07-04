<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Contracts\ExportableModule;
use App\Modules\Reports\Services\ExportProviderService;
use App\Services\ProviderDiscovery;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * ExportProviderService applica il controllo permessi sulle sorgenti, la whitelist
 * di ordinamento e il cap sul limite. I provider sono iniettati tramite un mock di
 * ProviderDiscovery, così i test non dipendono dai moduli reali.
 */
class ExportProviderServiceTest extends TestCase
{
    use MakesContainer;

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function fakeProvider(): ExportableModule
    {
        return new class () implements ExportableModule {
            public array $lastFetch = [];

            public function getDataSources(): array
            {
                return [
                    [
                        'key' => 'public', 'label' => 'Pubblica', 'icon' => 'fa', 'permission' => null,
                        'fields' => [
                            ['name' => 'nome', 'label' => 'Nome', 'sortable' => true],
                            ['name' => 'segreto', 'label' => 'Segreto', 'sortable' => false],
                        ],
                    ],
                    [
                        'key' => 'restricted', 'label' => 'Riservata', 'icon' => 'fa', 'permission' => 'contacts.view',
                        'fields' => [['name' => 'created_at', 'label' => 'Data', 'sortable' => true]],
                    ],
                ];
            }

            public function getExportData(string $sourceKey, array $filters = [], string $sortBy = 'created_at', string $sortDir = 'DESC', int $limit = 10000): array
            {
                $this->lastFetch = ['sort' => $sortBy, 'dir' => $sortDir, 'limit' => $limit];
                return [['nome' => 'x']];
            }

            public function getExportModuleName(): string
            {
                return 'Demo';
            }

            public function getExportModuleIcon(): string
            {
                return 'fa-demo';
            }

            public function getSingleRecord(string $sourceKey, int $recordId): ?array
            {
                return $recordId === 1 ? ['id' => 1] : null;
            }
        };
    }

    private function service(ExportableModule $provider): ExportProviderService
    {
        $discovery = $this->createMock(ProviderDiscovery::class);
        $discovery->method('discover')->willReturn(['Demo' => [$provider]]);

        $this->freshContainer();
        $this->bindInstance(ProviderDiscovery::class, $discovery);
        return new ExportProviderService();
    }

    public function testGetSourcesForUserHidesSourcesWithoutPermission(): void
    {
        $service = $this->service($this->fakeProvider());

        $result = $service->getSourcesForUser(['permissions' => [], 'roles' => []]);
        $keys = array_column($result[0]['sources'], 'key');
        $this->assertContains('public', $keys);
        $this->assertNotContains('restricted', $keys);
    }

    public function testAdminSeesAllSources(): void
    {
        $service = $this->service($this->fakeProvider());

        $result = $service->getSourcesForUser(['permissions' => [], 'roles' => ['admin']]);
        $keys = array_column($result[0]['sources'], 'key');
        $this->assertContains('public', $keys);
        $this->assertContains('restricted', $keys);
    }

    public function testSourceExists(): void
    {
        $service = $this->service($this->fakeProvider());
        $this->assertTrue($service->sourceExists('Demo', 'public'));
        $this->assertFalse($service->sourceExists('Demo', 'inesistente'));
        $this->assertFalse($service->sourceExists('Altro', 'public'));
    }

    public function testFetchDataRejectsUnpermittedSource(): void
    {
        $service = $this->service($this->fakeProvider());
        $_SESSION['user_permissions'] = [];
        $_SESSION['user_roles'] = [];

        $this->expectException(\RuntimeException::class);
        $service->fetchData('Demo', 'restricted');
    }

    public function testFetchDataFallsBackToCreatedAtForNonWhitelistedSort(): void
    {
        $provider = $this->fakeProvider();
        $service = $this->service($provider);
        $_SESSION['user_roles'] = ['admin'];

        // 'segreto' non è sortable → deve ricadere su created_at.
        $service->fetchData('Demo', 'public', [], 'segreto', 'ASC');
        $this->assertSame('created_at', $provider->lastFetch['sort']);
        $this->assertSame('ASC', $provider->lastFetch['dir']);

        // 'nome' è sortable → mantenuto.
        $service->fetchData('Demo', 'public', [], 'nome', 'DESC');
        $this->assertSame('nome', $provider->lastFetch['sort']);
    }

    public function testFetchDataCapsLimit(): void
    {
        $provider = $this->fakeProvider();
        $service = $this->service($provider);
        $_SESSION['user_roles'] = ['admin'];

        $service->fetchData('Demo', 'public', [], 'nome', 'ASC', 999999);
        $this->assertSame(10000, $provider->lastFetch['limit']);
    }

    public function testFetchDataThrowsForUnknownModule(): void
    {
        $service = $this->service($this->fakeProvider());
        $this->expectException(\RuntimeException::class);
        $service->fetchData('Inesistente', 'public');
    }
}
