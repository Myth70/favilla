<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\PermissionRepository;
use App\Modules\Admin\Repositories\RoleRepository;
use App\Services\AuditService;
use App\Services\UserService;
use InvalidArgumentException;
use RuntimeException;

class RoleService
{
    private RoleRepository       $roleRepo;
    private PermissionRepository $permRepo;

    public function __construct()
    {
        $this->roleRepo = app(RoleRepository::class);
        $this->permRepo = app(PermissionRepository::class);
    }

    public function listWithUserCount(): array
    {
        return $this->roleRepo->listWithUserCount();
    }

    public function countUsersForRole(int $roleId): int
    {
        return (int) $this->roleRepo->countUsers($roleId);
    }

    /**
     * @throws RuntimeException se il ruolo non esiste
     */
    public function findOrFail(int $id): array
    {
        $role = $this->roleRepo->find($id);
        if (!$role) {
            throw new RuntimeException('Ruolo non trovato.');
        }
        return $role;
    }

    /**
     * @throws InvalidArgumentException se lo slug è già in uso
     */
    public function create(array $data): int
    {
        if ($this->roleRepo->findBySlug($data['slug'])) {
            throw new InvalidArgumentException('Slug già in uso.');
        }
        $id = $this->roleRepo->create($data);
        AuditService::log('role_created', 'role', $id, null, $data);
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $old = $this->findOrFail($id);
        $oldData = ['name' => $old['name'], 'description' => $old['description'] ?? ''];
        $this->roleRepo->update($id, $data);
        AuditService::log('role_updated', 'role', $id, $oldData, $data);
    }

    /**
     * @throws RuntimeException se il ruolo è protetto o ha utenti assegnati
     */
    public function delete(int $id): void
    {
        $role = $this->findOrFail($id);

        if ($role['slug'] === 'admin') {
            throw new RuntimeException('Impossibile eliminare il ruolo admin di sistema.');
        }

        $userCount = $this->roleRepo->countUsers($id);
        if ($userCount > 0) {
            throw new RuntimeException(
                "Impossibile eliminare: il ruolo {$role['name']} ha utenti assegnati."
            );
        }

        $this->roleRepo->delete($id);
        AuditService::log('role_deleted', 'role', $id, [
            'name' => $role['name'],
            'slug' => $role['slug'],
        ], null);
    }

    /**
     * Duplica un ruolo esistente con tutti i suoi permessi come base per uno nuovo.
     * Genera uno slug univoco derivato (gestendo le collisioni) e ritorna l'ID
     * del nuovo ruolo.
     *
     * @throws RuntimeException se il ruolo sorgente non esiste
     */
    public function cloneRole(int $id): int
    {
        $role = $this->findOrFail($id);

        $baseSlug = $role['slug'] . '-copia';
        $slug     = $baseSlug;
        $suffix   = 2;
        while ($this->roleRepo->findBySlug($slug) !== null) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $newName = $role['name'] . ' (copia)';
        $newId   = $this->roleRepo->create([
            'name'        => $newName,
            'slug'        => $slug,
            'description' => $role['description'] ?? '',
        ]);

        $permissionIds = $this->roleRepo->getAssignedPermissionIds($id);
        if ($permissionIds) {
            $this->roleRepo->setPermissions($newId, $permissionIds);
        }

        AuditService::log('role_cloned', 'role', $newId, null, [
            'source_role_id' => $id,
            'name'           => $newName,
            'slug'           => $slug,
            'permissions'    => count($permissionIds),
        ]);

        return $newId;
    }

    /**
     * Restituisce i dati per il pannello permessi del form ruolo.
     */
    public function getPermissionsPayload(int $roleId): array
    {
        return [
            'grouped'     => $this->permRepo->getAllGroupedExcludingUnmanageable(),
            'assignedIds' => array_flip($this->roleRepo->getAssignedPermissionIds($roleId)),
        ];
    }

    /**
     * Sostituisce i permessi del ruolo e invalida le sessioni degli utenti coinvolti.
     */
    public function updatePermissions(int $roleId, array $permissionIds): void
    {
        $this->roleRepo->setPermissions($roleId, $permissionIds);

        AuditService::log('permissions_updated', 'role', $roleId, null, [
            'permission_ids' => $permissionIds,
        ]);

        $userService = app(UserService::class);
        foreach ($this->roleRepo->getUserIdsByRole($roleId) as $userId) {
            $userService->invalidateUserSessions((int) $userId);
        }
    }
}
