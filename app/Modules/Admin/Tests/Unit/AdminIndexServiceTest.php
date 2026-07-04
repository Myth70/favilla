<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Core\Router;
use App\Modules\Admin\Services\AdminIndexService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * AdminIndexService costruisce il catalogo admin e lo filtra per permessi/ruoli.
 * resolveRoute() passa per Router::url(): lo mockiamo perché in test non ci sono
 * rotte registrate, altrimenti ogni link verrebbe scartato.
 */
class AdminIndexServiceTest extends TestCase
{
    use MakesContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $router = $this->createMock(Router::class);
        $router->method('url')->willReturn('/x');

        $this->freshContainer();
        $this->bindInstance(Router::class, $router);
    }

    public function testAdminSeesFullCatalog(): void
    {
        $catalog = (new AdminIndexService())->getCatalog([], ['admin']);

        $this->assertGreaterThanOrEqual(2, $catalog['summary']['sections']);
        $this->assertGreaterThan(0, $catalog['summary']['links']);
        $this->assertNotEmpty($catalog['sections']);
    }

    public function testNonAdminSeesOnlyPermittedLinks(): void
    {
        $flat = (new AdminIndexService())->getFlatCatalog(['admin.users.view'], []);

        $labels = array_column($flat, 'label');
        // 'Utenti' richiede admin.users.view → visibile.
        $this->assertContains('Utenti', $labels);
        // 'Configurazione' richiede admin.settings.manage → nascosto.
        $this->assertNotContains('Configurazione', $labels);
    }

    public function testNonAdminWithoutPermissionsSeesNoPermissionedLinks(): void
    {
        $catalog = (new AdminIndexService())->getCatalog([], []);

        // Tutti i link del catalogo hardcoded hanno un permesso → nessuno visibile.
        $this->assertSame(0, $catalog['summary']['links']);
        $this->assertSame([], $catalog['sections']);
    }

    public function testFlatCatalogEntriesCarrySectionAndGroupContext(): void
    {
        $flat = (new AdminIndexService())->getFlatCatalog([], ['admin']);

        $this->assertNotEmpty($flat);
        $first = $flat[0];
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('url', $first);
        $this->assertArrayHasKey('group', $first);
        $this->assertArrayHasKey('section', $first);
        $this->assertArrayHasKey('search_text', $first);
    }
}
