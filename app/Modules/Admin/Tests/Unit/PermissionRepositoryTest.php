<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\PermissionRepository;
use App\Support\ConfigCache;
use Tests\ModuleTestCase;
use Tests\Support\BuildsRbacFixtures;

class PermissionRepositoryTest extends ModuleTestCase
{
    use BuildsRbacFixtures;

    private PermissionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRbacSchema();
        $this->repo = new PermissionRepository();
    }

    protected function tearDown(): void
    {
        config_flush(); // evita contaminazione del config tra test
        parent::tearDown();
    }

    public function testGetAllGroupedGroupsByModuleAndOrders(): void
    {
        ConfigCache::$data['modules'] = []; // nessun modulo core da escludere

        $this->makePermission('contacts.view', 'Contacts');
        $this->makePermission('contacts.edit', 'Contacts');
        $this->makePermission('tasks.view', 'Tasks');

        $grouped = $this->repo->getAllGroupedExcludingUnmanageable();

        $this->assertArrayHasKey('Contacts', $grouped);
        $this->assertArrayHasKey('Tasks', $grouped);
        $this->assertCount(2, $grouped['Contacts']);
        $this->assertCount(1, $grouped['Tasks']);
        // Ordinati per name dentro al gruppo (ORDER BY module, name).
        $this->assertSame('contacts.edit', $grouped['Contacts'][0]['name']);
    }

    public function testGetAllGroupedExcludesCoreUnmanageableModules(): void
    {
        ConfigCache::$data['modules'] = [
            ['name' => 'Auth', 'core' => true, 'permissions_manageable' => false],
            ['name' => 'Contacts', 'core' => false],
        ];

        $this->makePermission('auth.login', 'Auth');
        $this->makePermission('contacts.view', 'Contacts');

        $grouped = $this->repo->getAllGroupedExcludingUnmanageable();

        $this->assertArrayNotHasKey('Auth', $grouped, 'I moduli core non gestibili vanno esclusi');
        $this->assertArrayHasKey('Contacts', $grouped);
    }

    public function testGetAllGroupedUsesAltroFallbackForNullModule(): void
    {
        ConfigCache::$data['modules'] = [];
        $this->makePermission('orfano.perm', null);

        $grouped = $this->repo->getAllGroupedExcludingUnmanageable();

        $this->assertArrayHasKey('Altro', $grouped);
        $this->assertSame('orfano.perm', $grouped['Altro'][0]['name']);
    }

    public function testCreateHonorsFillable(): void
    {
        $id = $this->repo->create([
            'name'   => 'reports.view',
            'slug'   => 'reports.view',
            'module' => 'Reports',
            'id'     => 7777, // non-fillable
        ]);

        $row = $this->repo->find($id);
        $this->assertSame('reports.view', $row['slug']);
        $this->assertNotSame(7777, (int) $row['id']);
    }
}
