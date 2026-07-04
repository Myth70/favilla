<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\UserRepository;
use App\Security\RateLimiter;
use App\Services\AuthService;
use Tests\ModuleTestCase;

/**
 * Testa AuthService::refreshPermissions() in isolamento.
 * Garantisce che dopo un sync globale dei permessi la sessione venga aggiornata
 * senza richiedere logout/login.
 */
class AuthServiceRefreshPermissionsTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Schema minimo per UserRepository::findWithPermissions
        $this->migrate("
            CREATE TABLE users (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                name                 TEXT NOT NULL,
                email                TEXT,
                username             TEXT,
                password             TEXT,
                avatar_path          TEXT,
                is_active            INTEGER DEFAULT 1,
                must_change_password INTEGER DEFAULT 0,
                deleted_at           TEXT DEFAULT NULL,
                remember_token       TEXT DEFAULT NULL,
                created_at           TEXT DEFAULT (datetime('now')),
                updated_at           TEXT DEFAULT (datetime('now'))
            )
        ");
        $this->migrate('
            CREATE TABLE roles (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                slug TEXT
            )
        ');
        $this->migrate('
            CREATE TABLE permissions (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                name   TEXT,
                slug   TEXT UNIQUE,
                module TEXT
            )
        ');
        $this->migrate('
            CREATE TABLE user_role (
                user_id INTEGER,
                role_id INTEGER,
                PRIMARY KEY (user_id, role_id)
            )
        ');
        $this->migrate('
            CREATE TABLE role_permission (
                role_id       INTEGER,
                permission_id INTEGER,
                PRIMARY KEY (role_id, permission_id)
            )
        ');

        // Registra UserRepository e RateLimiter (dipendenze di AuthService costruttore)
        app()->instance(UserRepository::class, new UserRepository());
        app()->instance(RateLimiter::class, new RateLimiter());
    }

    public function test_refresh_permissions_updates_session_from_db(): void
    {
        $userId = $this->insertRow('users', [
            'name'     => 'Mario',
            'email'    => 'mario@test.local',
            'username' => 'mario',
            'password' => 'hash',
        ]);
        $roleId = $this->insertRow('roles', ['name' => 'Editor', 'slug' => 'editor']);
        $permId = $this->insertRow('permissions', ['name' => 'Vedi', 'slug' => 'contacts.view', 'module' => 'Contatti']);
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => $roleId]);
        $this->insertRow('role_permission', ['role_id' => $roleId, 'permission_id' => $permId]);

        // Simula sessione esistente con permessi "vecchi"
        $_SESSION['user_permissions']       = ['old.perm'];
        $_SESSION['user_roles']              = ['old_role'];
        $_SESSION['_permissions_loaded_at']  = time() - 3600;

        $service = new AuthService();
        $ok      = $service->refreshPermissions($userId);

        $this->assertTrue($ok);
        $this->assertContains('contacts.view', $_SESSION['user_permissions']);
        $this->assertNotContains('old.perm', $_SESSION['user_permissions']);
        $this->assertContains('editor', $_SESSION['user_roles']);
        $this->assertGreaterThan(time() - 5, (int) $_SESSION['_permissions_loaded_at']);
    }

    public function test_refresh_permissions_returns_false_if_user_missing(): void
    {
        $service = new AuthService();
        $this->assertFalse($service->refreshPermissions(99999));
    }

    public function test_refresh_permissions_picks_up_new_permissions_added_to_role(): void
    {
        $userId = $this->insertRow('users', ['name' => 'A', 'username' => 'a']);
        $roleId = $this->insertRow('roles', ['name' => 'R', 'slug' => 'r']);
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => $roleId]);

        $service = new AuthService();

        // Prima chiamata: nessun permesso assegnato al ruolo
        $service->refreshPermissions($userId);
        $this->assertSame([], $_SESSION['user_permissions']);

        // Aggiungo un permesso (come farebbe PermissionSyncService + role mapping)
        $permId = $this->insertRow('permissions', ['slug' => 'new.perm', 'name' => 'Nuovo', 'module' => 'X']);
        $this->insertRow('role_permission', ['role_id' => $roleId, 'permission_id' => $permId]);

        // Refresh: la sessione riflette il cambio senza logout
        $service->refreshPermissions($userId);
        $this->assertContains('new.perm', $_SESSION['user_permissions']);
    }
}
