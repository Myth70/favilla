<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Admin\Repositories\AdminUserRepository;

/**
 * Copre i costrutti MySQL-only di AdminUserRepository che SQLite non riproduce:
 *   - `GROUP_CONCAT(col ORDER BY col SEPARATOR ', ')` (SQLite non supporta
 *     né ORDER BY interno né la keyword SEPARATOR) per l'aggregazione ruoli;
 *   - `INSERT IGNORE` (errore di sintassi su SQLite) per l'assegnazione ruoli
 *     e il record preferenze.
 */
final class AdminUserRepositoryIntegrationTest extends DatabaseIntegrationTestCase
{
    private function repo(): AdminUserRepository
    {
        return new AdminUserRepository();
    }

    private function makeUser(string $suffix): int
    {
        return $this->insertRow('users', [
            'name'     => "User {$suffix}",
            'email'    => "user{$suffix}@example.test",
            'username' => "user{$suffix}",
            'password' => 'x',
        ]);
    }

    public function testListWithRolesAggregatesRolesInAlphabeticalOrder(): void
    {
        $userId = $this->makeUser('a');

        // Inserite in ordine non alfabetico: la query deve riordinarle.
        $zeta  = $this->insertRow('roles', ['name' => 'Zeta',  'slug' => 'zeta']);
        $alpha = $this->insertRow('roles', ['name' => 'Alpha', 'slug' => 'alpha']);

        $this->repo()->bulkAssignRole([$userId], $zeta);
        $this->repo()->bulkAssignRole([$userId], $alpha);

        $result = $this->repo()->listWithRoles([], 1, 25);

        $this->assertSame(1, $result['total']);
        $row = $result['items'][0];
        $this->assertSame('Alpha, Zeta', $row['roles_list'], 'GROUP_CONCAT deve ordinare i ruoli per nome');
        $this->assertSame('alpha,zeta', $row['roles_slugs']);
        $this->assertNull($row['last_login']);
    }

    public function testBulkAssignRoleIsIdempotent(): void
    {
        $userId = $this->makeUser('b');
        $roleId = $this->insertRow('roles', ['name' => 'Manager', 'slug' => 'manager']);

        $first  = $this->repo()->bulkAssignRole([$userId], $roleId);
        $second = $this->repo()->bulkAssignRole([$userId], $roleId);

        $this->assertSame(1, $first, 'la prima assegnazione inserisce una riga');
        $this->assertSame(0, $second, 'INSERT IGNORE non deve duplicare l\'assegnazione');
        $this->assertSame([$roleId], $this->repo()->getUserRoleIds($userId));
    }

    public function testEnsureUserPreferencesIsIdempotent(): void
    {
        $userId = $this->makeUser('c');

        $this->repo()->ensureUserPreferences($userId);
        $this->repo()->ensureUserPreferences($userId); // INSERT IGNORE: nessuna eccezione

        $count = (int) self::$pdo
            ->query("SELECT COUNT(*) FROM user_preferences WHERE user_id = {$userId}")
            ->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testSearchFilterMatchesAcrossFields(): void
    {
        $matchId = $this->makeUser('needle');
        $this->makeUser('other');

        $result = $this->repo()->listWithRoles(['search' => 'needle'], 1, 25);

        $this->assertSame(1, $result['total']);
        $this->assertSame($matchId, (int) $result['items'][0]['id']);
    }
}
