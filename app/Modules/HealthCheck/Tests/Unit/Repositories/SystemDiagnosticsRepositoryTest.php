<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Repositories;

use App\Modules\HealthCheck\Repositories\SystemDiagnosticsRepository;
use Tests\ModuleTestCase;

/**
 * Copre i metodi del repository eseguibili su SQLite (migrazioni, hash admin).
 * Le query information_schema/SHOW sono specifiche MariaDB e sono coperte dai
 * test dei check con repository mockato (vedi DatabaseCheckTest).
 */
class SystemDiagnosticsRepositoryTest extends ModuleTestCase
{
    private SystemDiagnosticsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE migrations (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                module   TEXT NULL
            )
        ');
        $this->migrate("
            CREATE TABLE users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                password   TEXT NOT NULL DEFAULT '',
                deleted_at TEXT NULL
            )
        ");
        $this->migrate('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL)');
        $this->migrate('CREATE TABLE user_role (user_id INTEGER, role_id INTEGER)');

        $this->repo = new SystemDiagnosticsRepository();
    }

    public function testExecutedCoreMigrationsReturnsOnlyCoreRows(): void
    {
        $this->insertRow('migrations', ['filename' => '001_core.sql']);
        $this->insertRow('migrations', ['filename' => '002_core.sql']);
        $this->insertRow('migrations', ['filename' => '001_tasks.sql', 'module' => 'Tasks']);

        $core = $this->repo->executedCoreMigrations();

        $this->assertEqualsCanonicalizing(['001_core.sql', '002_core.sql'], $core);
    }

    public function testExecutedModuleMigrationsGroupedByModule(): void
    {
        $this->insertRow('migrations', ['filename' => '001_core.sql']);
        $this->insertRow('migrations', ['filename' => '001_tasks.sql', 'module' => 'Tasks']);
        $this->insertRow('migrations', ['filename' => '002_tasks.sql', 'module' => 'Tasks']);
        $this->insertRow('migrations', ['filename' => '001_files.sql', 'module' => 'Files']);

        $map = $this->repo->executedModuleMigrations();

        $this->assertArrayNotHasKey('', $map);
        $this->assertEqualsCanonicalizing(['001_tasks.sql', '002_tasks.sql'], $map['Tasks']);
        $this->assertSame(['001_files.sql'], $map['Files']);
    }

    public function testAdminPasswordHashesReturnsOnlyActiveAdmins(): void
    {
        $adminRole = $this->insertRow('roles', ['slug' => 'admin']);
        $userRole  = $this->insertRow('roles', ['slug' => 'user']);

        $admin   = $this->insertRow('users', ['password' => 'HASH_ADMIN']);
        $plain   = $this->insertRow('users', ['password' => 'HASH_USER']);
        $deleted = $this->insertRow('users', ['password' => 'HASH_DELETED', 'deleted_at' => '2026-01-01 00:00:00']);

        $this->insertRow('user_role', ['user_id' => $admin, 'role_id' => $adminRole]);
        $this->insertRow('user_role', ['user_id' => $plain, 'role_id' => $userRole]);
        $this->insertRow('user_role', ['user_id' => $deleted, 'role_id' => $adminRole]);

        $hashes = $this->repo->adminPasswordHashes(10);

        $this->assertSame(['HASH_ADMIN'], $hashes);
    }
}
