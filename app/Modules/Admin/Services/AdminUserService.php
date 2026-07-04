<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\EventDispatcher;
use App\Events\UserCreated;
use App\Modules\Admin\Repositories\AdminUserRepository;
use App\Services\AuditService;
use App\Services\UserService;

class AdminUserService
{
    private AdminUserRepository $repo;

    public function __construct()
    {
        $this->repo = app(AdminUserRepository::class);
    }

    public function listWithRoles(array $filters, int $page): array
    {
        return $this->repo->listWithRoles($filters, $page);
    }

    public function getRoles(): array
    {
        return $this->repo->getRoles();
    }

    public function findWithPermissions(int $userId): ?array
    {
        return $this->repo->findWithPermissions($userId);
    }

    public function find(int $userId): ?array
    {
        return $this->repo->find($userId);
    }

    public function create(array $data): int
    {
        $userId = $this->repo->create($data);
        EventDispatcher::getInstance()->dispatch(new UserCreated($userId, $data['email'] ?? ''));
        return $userId;
    }

    /**
     * Creazione completa (utente + preferenze + ruoli) in transazione.
     * L'evento UserCreated parte solo a commit avvenuto.
     */
    public function createWithSetup(array $data, array $roleIds): int
    {
        $userId = $this->repo->createWithSetup($data, $roleIds);
        EventDispatcher::getInstance()->dispatch(new UserCreated($userId, $data['email'] ?? ''));
        return $userId;
    }

    public function update(int $userId, array $data): bool
    {
        return $this->repo->update($userId, $data);
    }

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        return $this->repo->emailExists($email, $excludeId);
    }

    public function usernameExists(string $username, int $excludeId = 0): bool
    {
        return $this->repo->usernameExists($username, $excludeId);
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->repo->syncRoles($userId, $roleIds);
    }

    public function ensureUserPreferences(int $userId): void
    {
        $this->repo->ensureUserPreferences($userId);
    }

    public function getUserRoleIds(int $userId): array
    {
        return $this->repo->getUserRoleIds($userId);
    }

    public function bulkSetActive(array $ids, int $state): int
    {
        return $this->repo->bulkSetActive($ids, $state);
    }

    public function bulkAssignRole(array $ids, int $roleId): int
    {
        return $this->repo->bulkAssignRole($ids, $roleId);
    }

    /**
     * Reset password admin: genera una password temporanea (delega a UserService)
     * e registra l'audit a esito positivo. Ritorna il risultato grezzo.
     *
     * @return array{success:bool, error?:string, user?:array, temp_password?:string}
     */
    public function resetPassword(int $userId): array
    {
        $result = app(UserService::class)->adminResetPassword($userId);

        if (!empty($result['success'])) {
            AuditService::log('password_reset', 'user', $userId, null, [
                'name' => $result['user']['name'] ?? '',
            ]);
        }

        return $result;
    }

    /**
     * Esegue un'azione di massa sugli utenti (activate/deactivate/assign_role).
     * Esclude l'account dell'admin corrente, invalida le sessioni dove necessario
     * e registra l'audit. Ritorna il payload per la risposta JSON del controller.
     *
     * @param int[] $ids        ID utente grezzi (filtrati internamente)
     * @param int   $selfId     ID dell'admin corrente, escluso dall'operazione
     * @param int|null $roleId  Ruolo da assegnare (solo per assign_role)
     * @return array{success:bool, message:string, count?:int, status?:int}
     */
    public function bulkAction(string $action, array $ids, int $selfId, ?int $roleId = null): array
    {
        $ids = array_values(array_filter(
            array_map('intval', $ids),
            static fn ($id) => $id > 0 && $id !== $selfId
        ));

        if ($ids === []) {
            return ['success' => false, 'message' => 'Operazione non applicabile al proprio account.', 'status' => 422];
        }

        switch ($action) {
            case 'activate':
                $count = $this->repo->bulkSetActive($ids, 1);
                AuditService::log('bulk_user_activated', 'user', null, null, ['ids' => $ids]);
                return [
                    'success' => true,
                    'message' => "{$count} " . ($count === 1 ? 'utente attivato.' : 'utenti attivati.'),
                    'count'   => $count,
                ];

            case 'deactivate':
                $count = $this->repo->bulkSetActive($ids, 0);
                $this->invalidateSessions($ids);
                AuditService::log('bulk_user_deactivated', 'user', null, null, ['ids' => $ids]);
                return [
                    'success' => true,
                    'message' => "{$count} " . ($count === 1 ? 'utente disattivato.' : 'utenti disattivati.'),
                    'count'   => $count,
                ];

            case 'assign_role':
                if (!$roleId) {
                    return ['success' => false, 'message' => 'Ruolo non specificato.', 'status' => 422];
                }
                $count = $this->repo->bulkAssignRole($ids, $roleId);
                $this->invalidateSessions($ids);
                AuditService::log('bulk_role_assigned', 'user', null, null, ['ids' => $ids, 'role_id' => $roleId]);
                return [
                    'success' => true,
                    'message' => 'Ruolo assegnato a ' . count($ids) . ' ' . (count($ids) === 1 ? 'utente.' : 'utenti.'),
                    'count'   => $count,
                ];
        }

        return ['success' => false, 'message' => 'Parametri non validi.', 'status' => 400];
    }

    /**
     * Invalida le sessioni attive degli utenti indicati (best-effort).
     *
     * @param int[] $ids
     */
    private function invalidateSessions(array $ids): void
    {
        $userSvc = app(UserService::class);
        foreach ($ids as $uid) {
            try {
                $userSvc->invalidateUserSessions($uid);
            } catch (\Throwable $e) {
                app_log('error', self::class . ': invalidateUserSessions failed for user ' . $uid . ': ' . $e->getMessage());
            }
        }
    }
}
