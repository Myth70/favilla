<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class RoleRepository extends BaseRepository
{
    protected string $table  = 'roles';
    protected array  $fillable = ['name', 'slug', 'description'];

    /**
     * Lista tutti i ruoli con il conteggio degli utenti assegnati.
     */
    public function listWithUserCount(): array
    {
        return $this->pdo->query('
            SELECT r.*, COUNT(ur.user_id) AS user_count
            FROM roles r
            LEFT JOIN user_role ur ON ur.role_id = r.id
            GROUP BY r.id
            ORDER BY r.name
        ')->fetchAll();
    }

    /**
     * Trova un ruolo per slug.
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Conta gli utenti assegnati a un ruolo.
     */
    public function countUsers(int $roleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_role WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Restituisce gli ID dei permessi assegnati al ruolo.
     */
    public function getAssignedPermissionIds(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT permission_id FROM role_permission WHERE role_id = ?'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Sostituisce atomicamente tutti i permessi del ruolo.
     */
    public function setPermissions(int $roleId, array $permissionIds): void
    {
        $this->transaction(function () use ($roleId, $permissionIds): void {
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = ?')
                ->execute([$roleId]);

            if ($permissionIds) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)'
                );
                foreach ($permissionIds as $permId) {
                    $stmt->execute([$roleId, $permId]);
                }
            }
        });
    }

    /**
     * Restituisce gli user_id degli utenti che hanno questo ruolo.
     */
    public function getUserIdsByRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM user_role WHERE role_id = ?'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
