<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Factory di fixture per lo schema RBAC (users / roles / permissions e pivot),
 * condiviso da molti test di Auth, Admin e dei servizi permessi.
 *
 * Centralizza le `CREATE TABLE` e gli insert finora duplicati in ogni test.
 * Le DDL sono in dialetto SQLite (compatibile con Tests\ModuleTestCase); le
 * colonne riflettono database/schema.sql per fedeltà.
 *
 * Richiede di essere usato in una classe che estende Tests\ModuleTestCase
 * (usa $this->migrate(), $this->insertRow(), $this->pdo).
 */
trait BuildsRbacFixtures
{
    /**
     * Crea le tabelle RBAC: users, roles, permissions, user_role, role_permission.
     */
    protected function createRbacSchema(): void
    {
        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                name                 TEXT    NOT NULL,
                email                TEXT    NOT NULL,
                username             TEXT    NOT NULL,
                password             TEXT    NOT NULL DEFAULT "",
                is_active            INTEGER NOT NULL DEFAULT 1,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                avatar_path          TEXT    DEFAULT NULL,
                remember_token       TEXT    DEFAULT NULL,
                password_changed_at  TEXT    DEFAULT NULL,
                created_at           TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at           TEXT    DEFAULT CURRENT_TIMESTAMP,
                deleted_at           TEXT    DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS roles (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                slug        TEXT    NOT NULL UNIQUE,
                description TEXT    DEFAULT NULL,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS permissions (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL,
                slug       TEXT    NOT NULL UNIQUE,
                module     TEXT    DEFAULT NULL,
                created_at TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT    DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, role_id)
            );
            CREATE TABLE IF NOT EXISTS role_permission (
                role_id       INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, permission_id)
            );
        ');
    }

    /**
     * @param array<string,mixed> $overrides
     */
    protected function makeUser(array $overrides = []): int
    {
        $n = random_int(1000, 999999);

        return $this->insertRow('users', array_merge([
            'name'     => 'Utente Test',
            'email'    => "user{$n}@example.test",
            'username' => "user{$n}",
            'password' => password_hash('secret-password', PASSWORD_DEFAULT),
        ], $overrides));
    }

    protected function makeRole(string $slug, ?string $name = null): int
    {
        return $this->insertRow('roles', [
            'name' => $name ?? ucfirst($slug),
            'slug' => $slug,
        ]);
    }

    protected function makePermission(string $slug, ?string $module = null): int
    {
        return $this->insertRow('permissions', [
            'name'   => $slug,
            'slug'   => $slug,
            'module' => $module,
        ]);
    }

    protected function assignRole(int $userId, int $roleId): void
    {
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => $roleId]);
    }

    protected function grantPermission(int $roleId, int $permissionId): void
    {
        $this->insertRow('role_permission', ['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
}
