<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services;

use App\Modules\Contacts\Repositories\CategoriesRepository;
use App\Modules\Contacts\Repositories\ContactsRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;
use App\Services\FileUploadService;
use App\Services\RoleResolver;

class ContactsService
{
    private ContactsRepository  $repo;
    private CategoriesRepository $catRepo;
    private RoleResolver        $roleResolver;

    public function __construct(
        ContactsRepository  $repo,
        CategoriesRepository $catRepo,
        RoleResolver        $roleResolver
    ) {
        $this->repo         = $repo;
        $this->catRepo      = $catRepo;
        $this->roleResolver = $roleResolver;
    }

    // ── Contatti CRUD ────────────────────────────────────────────────────────

    /**
     * @param int      $userId         contact owner
     * @param array    $filters        filter map (q, categoria_id, tag, sort, dir, preferiti, page)
     * @param string[] $userRoleSlugs  slug dei ruoli dell'utente corrente (passati dal Controller)
     */
    public function list(int $userId, array $filters = [], array $userRoleSlugs = []): array
    {
        return $this->repo->listPaginated($userId, $filters, $this->getRoleIdsBySlugs($userRoleSlugs));
    }

    /**
     * Show: include i contatti condivisi con un ruolo dell'utente.
     *
     * @param string[] $userRoleSlugs  slug dei ruoli dell'utente corrente (passati dal Controller)
     */
    public function find(int $id, int $userId, array $userRoleSlugs = []): ?array
    {
        return $this->repo->findAccessible($id, $userId, $this->getRoleIdsBySlugs($userRoleSlugs));
    }

    /** Owner-only: usato per gating di edit/update/delete/share. */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->repo->findForUser($id, $userId);
    }

    public function create(array $data, int $userId, ?array $avatarFile = null): int
    {
        $data['user_id'] = $userId;
        $data['tags']    = self::normalizeTags($data['tags'] ?? '');
        $this->normalizeGeoData($data);

        if (!empty($avatarFile['tmp_name'])) {
            try {
                $data['avatar'] = FileUploadService::uploadImage(
                    $avatarFile,
                    'contacts',
                    'ct_' . time() . '_',
                    2097152,
                    1000
                );
            } catch (\RuntimeException $e) {
                // avatar opzionale, continua senza
            }
        }

        $id = $this->repo->create($data);
        AuditService::log('contatto_created', 'contatto', $id, null, [
            'nome'    => trim(($data['nome'] ?? '') . ' ' . ($data['cognome'] ?? '')),
            'email'   => $data['email'] ?? '',
            'user_id' => $userId,
        ]);
        return $id;
    }

    public function update(int $id, array $data, int $userId, ?array $avatarFile = null): bool
    {
        $existing = $this->repo->findForUser($id, $userId);
        if (!$existing) {
            return false;
        }

        $data['tags'] = self::normalizeTags($data['tags'] ?? '');
        $this->normalizeGeoData($data);

        if (!empty($avatarFile['tmp_name'])) {
            try {
                $new = FileUploadService::uploadImage(
                    $avatarFile,
                    'contacts',
                    'ct_' . $id . '_',
                    2097152,
                    1000
                );
                if (!empty($existing['avatar'])) {
                    FileUploadService::delete($existing['avatar'], 'contacts');
                }
                $data['avatar'] = $new;
            } catch (\RuntimeException $e) {
                // keep existing
            }
        }

        $result = $this->repo->update($id, $data);
        if ($result) {
            AuditService::log(
                'contatto_updated',
                'contatto',
                $id,
                ['nome' => trim(($existing['nome'] ?? '') . ' ' . ($existing['cognome'] ?? ''))],
                ['nome' => trim(($data['nome'] ?? $existing['nome'] ?? '') . ' ' . ($data['cognome'] ?? $existing['cognome'] ?? ''))]
            );
        }
        return $result;
    }

    public function delete(int $id, int $userId): bool
    {
        $existing = $this->repo->findForUser($id, $userId);
        if (!$existing) {
            return false;
        }

        if (!empty($existing['avatar'])) {
            FileUploadService::delete($existing['avatar'], 'contacts');
        }

        // Prima che il cascade DB cancelli le righe di contact_recurrences,
        // elimina gli eventi calendario collegati (nessuna FK a calendar_events).
        if (isModuleEnabled('Calendar')) {
            try {
                $ricRepo = app(\App\Modules\Contacts\Repositories\RecurrencesRepository::class);
                $reminderSvc = app(\App\Modules\Contacts\Services\ContactsReminderService::class);
                foreach ($ricRepo->allForContatto($id) as $ric) {
                    if (!empty($ric['calendario_event_id'])) {
                        $reminderSvc->eliminaEventoCalendario(
                            (int) $ric['calendario_event_id'],
                            (int) $existing['user_id']
                        );
                    }
                }
            } catch (\Throwable) {
                // Non bloccare la cancellazione se il calendario è irraggiungibile
            }
        }

        $deleted = $this->repo->delete($id);
        if ($deleted) {
            AuditService::log('contatto_deleted', 'contatto', $id, [
                'nome'    => trim(($existing['nome'] ?? '') . ' ' . ($existing['cognome'] ?? '')),
                'email'   => $existing['email'] ?? '',
                'user_id' => $existing['user_id'] ?? $userId,
            ], null);
        }
        return $deleted;
    }

    public function deleteAvatar(int $id, int $userId): bool
    {
        $existing = $this->repo->findForUser($id, $userId);
        if (!$existing || empty($existing['avatar'])) {
            return false;
        }

        FileUploadService::delete($existing['avatar'], 'contacts');
        return $this->repo->update($id, ['avatar' => null]);
    }

    public function togglePreferito(int $id, int $userId): bool
    {
        return $this->repo->togglePreferito($id, $userId);
    }

    public function getPreferito(int $id, int $userId): bool
    {
        return $this->repo->getPreferito($id, $userId);
    }

    // ── Categorie ────────────────────────────────────────────────────────────

    public function getCategorie(int $userId): array
    {
        return $this->catRepo->allForUser($userId);
    }

    public function createCategoria(array $data, int $userId): int
    {
        $data['user_id'] = $userId;
        return $this->catRepo->create($data);
    }

    public function updateCategoria(int $id, array $data, int $userId): bool
    {
        $existing = $this->catRepo->findForUser($id, $userId);
        if (!$existing) {
            return false;
        }
        return $this->catRepo->update($id, $data);
    }

    public function deleteCategoria(int $id, int $userId): bool
    {
        $existing = $this->catRepo->findForUser($id, $userId);
        if (!$existing) {
            return false;
        }
        return $this->catRepo->delete($id);
    }

    // ── Sharing per ruolo ────────────────────────────────────────────────────

    /** Tutti i ruoli del sistema (ordinati per nome) — per il form di condivisione. */
    public function getAllRoles(): array
    {
        $roles = $this->roleResolver->all();
        usort($roles, fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));
        return $roles;
    }

    /**
     * Lista delle condivisioni di un contatto (owner-only).
     * @return array<int,array>|null  null se l'utente non è proprietario
     */
    public function getShares(int $contattoId, int $userId): ?array
    {
        if (!$this->repo->findForUser($contattoId, $userId)) {
            return null;
        }
        return $this->repo->listShares($contattoId);
    }

    public function getShareRoleIds(int $contattoId, int $userId): ?array
    {
        if (!$this->repo->findForUser($contattoId, $userId)) {
            return null;
        }
        return $this->repo->listShareRoleIds($contattoId);
    }

    /**
     * Sostituisce l'insieme di ruoli con cui il contatto è condiviso.
     * Idempotente per i ruoli già presenti, rimuove quelli non più selezionati.
     * Notifica i ruoli aggiunti.
     *
     * @param int[] $roleIds
     * @return array{added:int[], removed:int[]}|null  null se non proprietario
     */
    /**
     * @param string $sharerName  nome dell'utente che sta condividendo (passato dal Controller)
     * @return array{added:int[], removed:int[]}|null  null se il contatto non esiste o non appartiene a $userId
     */
    public function shareWithRoles(int $contattoId, int $userId, array $roleIds, string $sharerName = ''): ?array
    {
        $contatto = $this->repo->findForUser($contattoId, $userId);
        if (!$contatto) {
            return null;
        }

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        $current = $this->repo->listShareRoleIds($contattoId);

        $toAdd    = array_values(array_diff($roleIds, $current));
        $toRemove = array_values(array_diff($current, $roleIds));

        // Delta applicato in transazione (no stato incoerente su errore a metà).
        $this->repo->replaceShares($contattoId, $toAdd, $toRemove, $userId);

        // Notifica solo per i ruoli aggiunti (silenziosa se NotificationService fallisce)
        if (!empty($toAdd)) {
            $this->notifyShareToRoles($contatto, $userId, $toAdd, $sharerName !== '' ? $sharerName : 'Un utente');
        }

        return ['added' => $toAdd, 'removed' => $toRemove];
    }

    public function unshare(int $contattoId, int $userId, int $roleId): bool
    {
        if (!$this->repo->findForUser($contattoId, $userId)) {
            return false;
        }
        return $this->repo->removeShare($contattoId, $roleId);
    }

    // ── Utilità ──────────────────────────────────────────────────────────────

    public function getAllTags(int $userId): array
    {
        return $this->repo->getAllTags($userId);
    }

    public function getStats(int $userId): array
    {
        return $this->repo->getStats($userId);
    }

    public static function normalizeTags(string $raw): string
    {
        $parts = array_unique(array_filter(array_map('trim', explode(',', $raw))));
        return implode(', ', $parts);
    }

    private function normalizeGeoData(array &$data): void
    {
        $hasAddress = !empty(trim((string) ($data['indirizzo'] ?? '')));
        $hasLat = isset($data['latitude']) && $data['latitude'] !== null && $data['latitude'] !== '';
        $hasLng = isset($data['longitude']) && $data['longitude'] !== null && $data['longitude'] !== '';

        if (!$hasAddress || !$hasLat || !$hasLng) {
            $data['latitude'] = null;
            $data['longitude'] = null;
            $data['geocoding_source'] = null;
            $data['geocoded_at'] = null;
            return;
        }

        $data['latitude'] = round((float) $data['latitude'], 8);
        $data['longitude'] = round((float) $data['longitude'], 8);

        $source = (string) ($data['geocoding_source'] ?? 'manual');
        if (!in_array($source, ['manual', 'osm'], true)) {
            $source = 'manual';
        }

        $data['geocoding_source'] = $source;
        $data['geocoded_at'] = date('Y-m-d H:i:s');
    }

    /**
     * Risolve i role_ids da un array di slug usando il RoleResolver condiviso.
     * Il Service non legge $_SESSION: il Controller passa gli slug dell'utente corrente.
     *
     * @param string[] $slugs
     * @return int[]
     */
    public function getRoleIdsBySlugs(array $slugs): array
    {
        return $this->roleResolver->getIdsBySlugs($slugs);
    }

    /**
     * Notifica gli utenti di ciascun ruolo destinatario della condivisione.
     * Best-effort: ogni eccezione è swallowed per non bloccare la share.
     *
     * @param int[]  $roleIds
     * @param string $sharerName  nome dell'utente che ha condiviso, passato dal caller
     */
    private function notifyShareToRoles(array $contatto, int $sharedByUserId, array $roleIds, string $sharerName): void
    {
        if (!class_exists(NotificationService::class)) {
            return;
        }

        $nomeCont   = trim($contatto['nome'] . ' ' . ($contatto['cognome'] ?? ''));
        $link       = route('contacts.show', ['id' => $contatto['id']]);

        foreach ($roleIds as $roleId) {
            try {
                $role = $this->roleResolver->find($roleId);
                if (!$role) {
                    continue;
                }

                NotificationService::dispatchEventToRole(
                    'contacts.shared_with_role',
                    'Contacts',
                    $role['slug'],
                    [
                        'contatto_id'   => (int) $contatto['id'],
                        'contatto_nome' => $nomeCont,
                        'shared_by_name' => $sharerName,
                        'role_name'     => $role['name'],
                    ],
                    $link,
                    $sharedByUserId
                );
            } catch (\Throwable $e) {
                // non bloccare la share per errori di notifica
                app_log('error', self::class . ': dispatch share notification failed: ' . $e->getMessage());
            }
        }
    }

    // ── Helper statici usati anche nelle view ─────────────────────────────────

    public static function initials(string $nome, string $cognome = ''): string
    {
        $i = mb_strtoupper(mb_substr($nome, 0, 1));
        if ($cognome !== '') {
            $i .= mb_strtoupper(mb_substr($cognome, 0, 1));
        }
        return $i;
    }

    public static function avatarColor(string $nome): string
    {
        $palette = [
            '#3b82f6', '#ef4444', '#10b981', '#f59e0b',
            '#8b5cf6', '#06b6d4', '#ec4899', '#f97316',
            '#14b8a6', '#a855f7',
        ];
        $hash = 0;
        foreach (mb_str_split($nome) as $ch) {
            $hash = mb_ord($ch) + (($hash << 5) - $hash);
        }
        return $palette[abs($hash) % count($palette)];
    }
}
