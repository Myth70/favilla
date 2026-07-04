<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class IsAdminHelperTest extends TestCase
{
    private array $savedSession;

    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
    }

    public function testFalseWhenNoSession(): void
    {
        unset($_SESSION['user_roles']);
        $this->assertFalse(is_admin());
    }

    public function testFalseForNonAdminRoles(): void
    {
        $_SESSION['user_roles'] = ['user', 'manager'];
        $this->assertFalse(is_admin());
    }

    public function testTrueWhenAdminRolePresent(): void
    {
        $_SESSION['user_roles'] = ['user', 'admin'];
        $this->assertTrue(is_admin());
    }

    /**
     * is_admin() guarda i RUOLI, non i permessi: avere un permesso (anche di
     * gestione) non rende admin. È esattamente la distinzione che evita di
     * riusare has_permission() come override di ownership.
     */
    public function testPermissionDoesNotImplyAdmin(): void
    {
        $_SESSION['user_roles'] = ['user'];
        $_SESSION['user_permissions'] = ['calendar.edit', 'reports.admin'];
        $this->assertFalse(is_admin());
    }
}
