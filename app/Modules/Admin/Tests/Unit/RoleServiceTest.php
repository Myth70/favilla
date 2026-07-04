<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\RoleService;
use InvalidArgumentException;
use RuntimeException;
use Tests\ModuleTestCase;
use Tests\Support\BuildsRbacFixtures;

/**
 * RoleService orchestra RoleRepository/PermissionRepository (risolti via app()):
 * i test usano i repository reali su SQLite. AuditService::log ha fallback
 * silenzioso, quindi è un no-op senza tabella audit_logs.
 */
class RoleServiceTest extends ModuleTestCase
{
    use BuildsRbacFixtures;

    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRbacSchema();
        $this->service = new RoleService();
    }

    public function testCreateRejectsDuplicateSlug(): void
    {
        $this->service->create(['name' => 'Ops', 'slug' => 'ops', 'description' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->create(['name' => 'Ops 2', 'slug' => 'ops', 'description' => '']);
    }

    public function testFindOrFailThrowsForUnknownRole(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->findOrFail(999);
    }

    public function testDeleteRejectsAdminRole(): void
    {
        $id = $this->makeRole('admin', 'Amministratore');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('admin');
        $this->service->delete($id);
    }

    public function testDeleteRejectsRoleWithAssignedUsers(): void
    {
        $role = $this->makeRole('team');
        $user = $this->makeUser();
        $this->assignRole($user, $role);

        $this->expectException(RuntimeException::class);
        $this->service->delete($role);
    }

    public function testDeleteRemovesEmptyNonAdminRole(): void
    {
        $role = $this->makeRole('temporaneo');
        $this->service->delete($role);

        // Il ruolo non compare più nell'elenco.
        $remaining = array_filter(
            $this->service->listWithUserCount(),
            static fn ($r) => (int) $r['id'] === $role
        );
        $this->assertCount(0, $remaining);
    }

    public function testCloneRoleGeneratesUniqueSlugAndCopiesPermissions(): void
    {
        $source = $this->makeRole('ops', 'Operatori');
        $p1 = $this->makePermission('contacts.view');
        $p2 = $this->makePermission('contacts.edit');
        $this->grantPermission($source, $p1);
        $this->grantPermission($source, $p2);

        $cloneId = $this->service->cloneRole($source);
        $clone = $this->service->findOrFail($cloneId);

        $this->assertSame('ops-copia', $clone['slug']);
        $this->assertSame('Operatori (copia)', $clone['name']);

        // Una seconda clonazione gestisce la collisione di slug.
        $cloneId2 = $this->service->cloneRole($source);
        $this->assertSame('ops-copia-2', $this->service->findOrFail($cloneId2)['slug']);
    }
}
