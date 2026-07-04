<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\AdminUserRepository;
use Tests\ModuleTestCase;

class AdminUserRepositoryTest extends ModuleTestCase
{
    private AdminUserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE users (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                name                TEXT    NOT NULL,
                email               TEXT    NOT NULL,
                username            TEXT    NOT NULL,
                password            TEXT    NOT NULL DEFAULT '',
                is_active           INTEGER DEFAULT 1,
                must_change_password INTEGER DEFAULT 0,
                avatar_path         TEXT    DEFAULT NULL,
                remember_token      TEXT    DEFAULT NULL,
                created_at          TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at          TEXT    DEFAULT CURRENT_TIMESTAMP,
                deleted_at          TEXT    DEFAULT NULL
            )
        ");

        $this->migrate('
            CREATE TABLE roles (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                slug        TEXT    NOT NULL,
                description TEXT    DEFAULT NULL,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->migrate('
            CREATE TABLE user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, role_id)
            )
        ');

        $this->repo = new AdminUserRepository();
    }

    private function createUser(array $overrides = []): int
    {
        static $counter = 0;
        $counter++;
        $data = array_merge([
            'name'     => "User {$counter}",
            'email'    => "user{$counter}@test.com",
            'username' => "user{$counter}",
            'password' => 'hashed',
        ], $overrides);
        return $this->insertRow('users', $data);
    }

    private function createRole(string $name, string $slug): int
    {
        return $this->insertRow('roles', ['name' => $name, 'slug' => $slug]);
    }

    // ── listWithRoles ────────────────────────────────────────────
    // NOTA: listWithRoles() usa GROUP_CONCAT(... ORDER BY ... SEPARATOR ', ')
    // che è sintassi MySQL-only e non gira su SQLite. Questi test richiedono
    // un'integrazione con MariaDB. Qui testiamo solo syncRoles().

    // ── syncRoles ────────────────────────────────────────────────

    public function testSyncRolesAssignsRoles(): void
    {
        $userId = $this->createUser();
        $r1 = $this->createRole('Admin', 'admin');
        $r2 = $this->createRole('Editor', 'editor');

        $this->repo->syncRoles($userId, [$r1, $r2]);

        $stmt = $this->pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ? ORDER BY role_id');
        $stmt->execute([$userId]);
        $roleIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame([$r1, $r2], array_map('intval', $roleIds));
    }

    public function testSyncRolesReplacesExistingRoles(): void
    {
        $userId = $this->createUser();
        $r1 = $this->createRole('Admin', 'admin');
        $r2 = $this->createRole('Editor', 'editor');
        $r3 = $this->createRole('Viewer', 'viewer');

        $this->repo->syncRoles($userId, [$r1, $r2]);
        $this->repo->syncRoles($userId, [$r3]);

        $stmt = $this->pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ?');
        $stmt->execute([$userId]);
        $roleIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertCount(1, $roleIds);
        $this->assertSame($r3, (int) $roleIds[0]);
    }

    public function testSyncRolesWithEmptyArrayRemovesAllRoles(): void
    {
        $userId = $this->createUser();
        $r1 = $this->createRole('Admin', 'admin');

        $this->repo->syncRoles($userId, [$r1]);
        $this->repo->syncRoles($userId, []);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_role WHERE user_id = ?');
        $stmt->execute([$userId]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
