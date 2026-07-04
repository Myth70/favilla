<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\RoleRepository;
use Tests\ModuleTestCase;
use Tests\Support\BuildsRbacFixtures;

class RoleRepositoryTest extends ModuleTestCase
{
    use BuildsRbacFixtures;

    private RoleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRbacSchema();
        $this->repo = new RoleRepository();
    }

    public function testListWithUserCountReturnsCountsOrderedByName(): void
    {
        $admin  = $this->makeRole('admin', 'Amministratore');
        $editor = $this->makeRole('editor', 'Editor');
        $this->makeRole('viewer', 'Lettore'); // nessun utente

        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $this->assignRole($u1, $admin);
        $this->assignRole($u2, $admin);
        $this->assignRole($u1, $editor);

        $rows = $this->repo->listWithUserCount();

        // Ordinati per name: Amministratore, Editor, Lettore.
        $this->assertSame(['Amministratore', 'Editor', 'Lettore'], array_column($rows, 'name'));
        $byName = array_column($rows, 'user_count', 'name');
        $this->assertSame(2, (int) $byName['Amministratore']);
        $this->assertSame(1, (int) $byName['Editor']);
        $this->assertSame(0, (int) $byName['Lettore']);
    }

    public function testFindBySlugReturnsRoleOrNull(): void
    {
        $this->makeRole('manager', 'Manager');

        $found = $this->repo->findBySlug('manager');
        $this->assertNotNull($found);
        $this->assertSame('Manager', $found['name']);

        $this->assertNull($this->repo->findBySlug('inesistente'));
    }

    public function testCountUsersCountsOnlyAssignedRole(): void
    {
        $r1 = $this->makeRole('r1');
        $r2 = $this->makeRole('r2');
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $this->assignRole($u1, $r1);
        $this->assignRole($u2, $r1);
        $this->assignRole($u1, $r2);

        $this->assertSame(2, $this->repo->countUsers($r1));
        $this->assertSame(1, $this->repo->countUsers($r2));
    }

    public function testSetPermissionsReplacesAtomically(): void
    {
        $role = $this->makeRole('ops');
        $p1 = $this->makePermission('contacts.view');
        $p2 = $this->makePermission('contacts.edit');
        $p3 = $this->makePermission('contacts.delete');

        $this->repo->setPermissions($role, [$p1, $p2]);
        $this->assertEqualsCanonicalizing(
            [$p1, $p2],
            array_map('intval', $this->repo->getAssignedPermissionIds($role))
        );

        // Una seconda chiamata SOSTITUISCE l'insieme, non lo aggiunge.
        $this->repo->setPermissions($role, [$p3]);
        $this->assertSame([$p3], array_map('intval', $this->repo->getAssignedPermissionIds($role)));

        // Insieme vuoto rimuove tutti i permessi.
        $this->repo->setPermissions($role, []);
        $this->assertSame([], $this->repo->getAssignedPermissionIds($role));
    }

    public function testGetUserIdsByRoleReturnsAssignedUsers(): void
    {
        $role = $this->makeRole('team');
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $this->makeUser(); // non assegnato
        $this->assignRole($u1, $role);
        $this->assignRole($u2, $role);

        $this->assertEqualsCanonicalizing(
            [$u1, $u2],
            array_map('intval', $this->repo->getUserIdsByRole($role))
        );
    }

    public function testCreateHonorsFillableAndIgnoresUnknownColumns(): void
    {
        $id = $this->repo->create([
            'name'        => 'Nuovo Ruolo',
            'slug'        => 'nuovo-ruolo',
            'description' => 'desc',
            'id'          => 9999, // non-fillable, deve essere ignorato
        ]);

        $row = $this->repo->find($id);
        $this->assertNotNull($row);
        $this->assertSame('Nuovo Ruolo', $row['name']);
        $this->assertSame('nuovo-ruolo', $row['slug']);
        $this->assertNotSame(9999, (int) $row['id']);
    }
}
