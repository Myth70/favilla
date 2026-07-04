<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';

    /**
     * Find active user by email (for login).
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE email = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find active user by username (for login).
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE username = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find user by email or username (login accepts either).
     */
    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE (email = ? OR username = ?) AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Update password for a user.
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$hashedPassword, $userId]);
    }

    /**
     * Set must_change_password flag and update password.
     */
    public function setTemporaryPassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET password = ?, must_change_password = 1, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$hashedPassword, $userId]);
    }

    /**
     * Update user profile name.
     */
    public function updateName(int $userId, string $name): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET name = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$name, $userId]);
    }

    /**
     * Update user avatar path.
     */
    public function updateAvatar(int $userId, ?string $avatarPath): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET avatar_path = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$avatarPath, $userId]);
    }

    /**
     * Get user avatar path.
     */
    public function getAvatarPath(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT avatar_path FROM {$this->table} WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    /**
     * Get user preferences.
     */
    public function getPreferences(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get user with roles and permissions.
     */
    public function findWithPermissions(int $userId): ?array
    {
        $user = $this->find($userId);
        if (!$user) {
            return null;
        }

        // Fetch roles
        $stmt = $this->pdo->prepare(
            'SELECT r.* FROM roles r
             INNER JOIN user_role ur ON ur.role_id = r.id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        $user['roles'] = $stmt->fetchAll();

        // Fetch permissions (via roles)
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.slug FROM permissions p
             INNER JOIN role_permission rp ON rp.permission_id = p.id
             INNER JOIN user_role ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        $user['permissions'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $user;
    }
}
