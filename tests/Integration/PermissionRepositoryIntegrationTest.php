<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Admin\Repositories\PermissionRepository;

/**
 * importFromModule() usa `INSERT IGNORE`, sintassi MySQL non parsabile da SQLite:
 * va verificata sul dialetto reale. Testa l'idempotenza (re-import non duplica).
 */
class PermissionRepositoryIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testImportFromModuleIsIdempotent(): void
    {
        $repo = new PermissionRepository();
        $perms = [
            ['name' => 'demo.view', 'slug' => 'demo.view'],
            ['name' => 'demo.edit', 'slug' => 'demo.edit'],
        ];

        $first = $repo->importFromModule('Demo', $perms);
        $this->assertSame(2, $first, 'Il primo import inserisce entrambi i permessi');

        // Re-import: INSERT IGNORE non deve creare duplicati (slug è UNIQUE).
        $second = $repo->importFromModule('Demo', $perms);
        $this->assertSame(0, $second, 'Il re-import non deve inserire nulla');

        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM permissions WHERE slug LIKE 'demo.%'"
        )->fetchColumn();
        $this->assertSame(2, $count);
    }
}
