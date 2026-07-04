<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\NotificationService;
use Tests\ModuleTestCase;

/**
 * Tests for the static read helpers of NotificationService that run plain,
 * SQLite-compatible SQL against the users/roles tables.
 */
class NotificationServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                email      TEXT,
                is_active  INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT DEFAULT NULL
            )
        ');
        $this->migrate('
            CREATE TABLE roles (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            )
        ');

        $this->insertRow('users', ['name' => 'Bruno', 'email' => 'b@test.it', 'is_active' => 1]);
        $this->insertRow('users', ['name' => 'Disabled', 'email' => 'd@test.it', 'is_active' => 0]);
        $this->insertRow('users', ['name' => 'Deleted', 'email' => 'x@test.it', 'is_active' => 1, 'deleted_at' => '2026-01-01 00:00:00']);
        $this->insertRow('users', ['name' => 'Alba', 'email' => 'a@test.it', 'is_active' => 1]);
    }

    public function testIsValidUserOnlyMatchesActiveNonDeletedUsers(): void
    {
        $this->assertTrue(NotificationService::isValidUser(1), 'Utente attivo');
        $this->assertFalse(NotificationService::isValidUser(2), 'Utente disattivato');
        $this->assertFalse(NotificationService::isValidUser(3), 'Utente eliminato');
        $this->assertFalse(NotificationService::isValidUser(999), 'Utente inesistente');
    }

    public function testGetActiveUsersExcludesInactiveAndDeletedSortedByName(): void
    {
        $users = NotificationService::getActiveUsers();

        $names = array_column($users, 'name');
        $this->assertSame(['Alba', 'Bruno'], $names);
    }

    public function testGetAvailableRolesReturnsRolesSortedByName(): void
    {
        $this->insertRow('roles', ['name' => 'Operatore', 'slug' => 'operator']);
        $this->insertRow('roles', ['name' => 'Amministratore', 'slug' => 'admin']);

        $roles = NotificationService::getAvailableRoles();

        $this->assertSame(['Amministratore', 'Operatore'], array_column($roles, 'name'));
    }
}
