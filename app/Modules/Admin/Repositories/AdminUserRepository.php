<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Repositories\UserRepository;
use PDO;

class AdminUserRepository extends UserRepository
{
    /**
     * Lista utenti con ruoli aggregati, paginazione e filtri.
     * Esclude gli utenti soft-deleted (deleted_at IS NULL).
     */
    public function listWithRoles(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['u.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['role_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM user_role ur2
                                  WHERE ur2.user_id = u.id AND ur2.role_id = ?)';
            $params[] = (int) $filters['role_id'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $where[]  = 'u.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)';
            $q        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$q, $q, $q]);
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $sql = "
            SELECT u.id, u.name, u.email, u.username, u.is_active,
                   u.must_change_password, u.created_at, u.avatar_path,
                   GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles_list,
                   GROUP_CONCAT(r.slug ORDER BY r.name SEPARATOR ',') AS roles_slugs,
                   (SELECT la.created_at FROM login_attempts la
                    WHERE (la.email = u.email OR la.email = u.username) AND la.success = 1
                    ORDER BY la.created_at DESC LIMIT 1) AS last_login
            FROM users u
            LEFT JOIN user_role ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE {$whereClause}
            GROUP BY u.id
            ORDER BY u.name ASC
            LIMIT ? OFFSET ?
        ";

        $paginatedParams = array_merge($params, [$perPage, $offset]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($paginatedParams);
        $items = $stmt->fetchAll();

        $countSql = "SELECT COUNT(DISTINCT u.id) FROM users u
                     LEFT JOIN user_role ur ON ur.user_id = u.id
                     WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Crea un utente con preferenze e ruoli in un'unica transazione:
     * un crash a metà non lascia utenti senza preferenze o senza ruoli.
     */
    public function createWithSetup(array $data, array $roleIds): int
    {
        return $this->transaction(function () use ($data, $roleIds): int {
            $userId = $this->create($data);
            $this->ensureUserPreferences($userId);
            if (!empty($roleIds)) {
                $this->syncRoles($userId, $roleIds);
            }
            return $userId;
        });
    }

    /**
     * Sincronizza i ruoli di un utente rimpiazzando completamente quelli esistenti.
     * Chiamare invalidateUserSessions() separatamente dopo questo metodo.
     */
    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->pdo->prepare('DELETE FROM user_role WHERE user_id = ?')
            ->execute([$userId]);

        if (!empty($roleIds)) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_role (user_id, role_id) VALUES (?, ?)'
            );
            foreach ($roleIds as $roleId) {
                $stmt->execute([$userId, (int) $roleId]);
            }
        }
    }

    /**
     * Get all roles ordered by name (for dropdowns and display).
     */
    public function getRoles(): array
    {
        return $this->pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();
    }

    /**
     * Check if an email is already taken, optionally excluding a user ID.
     */
    public function emailExists(string $email, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL AND id != ? LIMIT 1'
        );
        $stmt->execute([$email, $excludeId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Check if a username is already taken, optionally excluding a user ID.
     */
    public function usernameExists(string $username, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE username = ? AND deleted_at IS NULL AND id != ? LIMIT 1'
        );
        $stmt->execute([$username, $excludeId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Assicura la presenza del record preferenze utente.
     */
    public function ensureUserPreferences(int $userId): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)')
            ->execute([$userId]);
    }

    /**
     * Restituisce gli ID ruolo correnti dell'utente.
     *
     * @return int[]
     */
    public function getUserRoleIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows ?: []);
    }

    public function bulkSetActive(array $ids, int $state): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE users SET is_active = ?, updated_at = NOW()
             WHERE id IN ($placeholders) AND deleted_at IS NULL"
        );
        $stmt->execute(array_merge([$state], $ids));
        return $stmt->rowCount();
    }

    public function bulkAssignRole(array $ids, int $roleId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $count = 0;
        $stmt  = $this->pdo->prepare(
            'INSERT IGNORE INTO user_role (user_id, role_id) VALUES (?, ?)'
        );
        foreach ($ids as $userId) {
            $stmt->execute([(int) $userId, $roleId]);
            $count += $stmt->rowCount();
        }
        return $count;
    }
}
