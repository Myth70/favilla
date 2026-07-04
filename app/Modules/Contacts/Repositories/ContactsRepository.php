<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Repositories;

use App\Repositories\BaseRepository;

class ContactsRepository extends BaseRepository
{
    protected string $table = 'contacts';

    private ?bool $usersTableAvailable = null;

    protected array $fillable = [
        'user_id', 'categoria_id', 'nome', 'cognome', 'azienda', 'ruolo',
        'email', 'telefono', 'telefono_alt', 'indirizzo', 'latitude', 'longitude',
        'geocoding_source', 'geocoded_at', 'sito_web',
        'linkedin', 'instagram', 'twitter', 'facebook', 'whatsapp', 'telegram',
        'avatar', 'tags', 'note', 'preferito',
    ];

    protected bool $timestamps = true;
    protected bool $auditable  = true;

    /**
     * Lista paginata con filtri.
     *
     * Visibilità: l'utente vede sempre i propri contatti (c.user_id = ?) e,
     * se ha almeno un ruolo, anche quelli condivisi via contact_shares con
     * uno qualsiasi dei suoi role_ids.
     *
     * @param int        $userId   utente corrente
     * @param array      $filters  q, categoria_id, tag, preferiti, sort, dir, page
     * @param int[]      $roleIds  role_ids dell'utente per la share visibility
     */
    public function listPaginated(int $userId, array $filters = [], array $roleIds = []): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 24;
        $offset  = ($page - 1) * $perPage;

        [$visSql, $visParams] = $this->buildVisibilityClause($userId, $roleIds);

        $where  = ['(' . $visSql . ')'];
        $params = $visParams;

        if (!empty($filters['q'])) {
            $like     = '%' . $filters['q'] . '%';
            $where[]  = '(c.nome LIKE ? OR c.cognome LIKE ? OR c.azienda LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)';
            $params   = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        if (!empty($filters['categoria_id'])) {
            $where[]  = 'c.categoria_id = ?';
            $params[] = (int) $filters['categoria_id'];
        }

        if (!empty($filters['tag'])) {
            $where[]  = 'c.tags LIKE ?';
            $params[] = '%' . $filters['tag'] . '%';
        }

        if (!empty($filters['preferiti'])) {
            // I preferiti sono per-utente proprietario: filtrare su c.preferito = 1
            // mostra solo i propri preferiti (i contatti condivisi non hanno
            // un flag "preferito" per chi li riceve).
            $where[]  = '(c.user_id = ? AND c.preferito = 1)';
            $params[] = $userId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $allowed = ['nome', 'cognome', 'azienda', 'created_at'];
        $sort    = in_array($filters['sort'] ?? '', $allowed, true) ? $filters['sort'] : 'nome';
        $dir     = strtolower($filters['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} c {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $ownerJoin = $this->hasUsersTable()
            ? 'LEFT JOIN users u_owner ON u_owner.id = c.user_id'
            : '';
        $ownerNameSelect = $this->hasUsersTable()
            ? 'u_owner.name AS owner_name'
            : 'NULL AS owner_name';

        $dataStmt = $this->pdo->prepare(
            "SELECT c.*,
                    cat.nome   AS categoria_nome,
                    cat.colore AS categoria_colore,
                    COALESCE(rc.cnt, 0) AS num_ricorrenze,
                    (c.user_id = ?) AS is_owner,
                    {$ownerNameSelect}
             FROM {$this->table} c
             LEFT JOIN contact_categories cat ON cat.id = c.categoria_id
             {$ownerJoin}
             LEFT JOIN (
                 SELECT contatto_id, COUNT(*) AS cnt
                 FROM contact_recurrences
                 GROUP BY contatto_id
             ) rc ON rc.contatto_id = c.id
             {$whereSql}
             ORDER BY c.{$sort} {$dir}
             {$this->limitClause($perPage, $offset)}"
        );
        $dataStmt->execute(array_merge([$userId], $params));

        return [
            'data'     => $dataStmt->fetchAll(),
            'total'    => $total,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
            'page'     => $page,
            'perPage'  => $perPage,
        ];
    }

    /**
     * Singolo contatto con dettagli, accessibile se owner o condiviso con uno dei roleIds.
     * Ritorna null se non trovato o non accessibile.
     */
    public function findAccessible(int $id, int $userId, array $roleIds = []): ?array
    {
        [$visSql, $visParams] = $this->buildVisibilityClause($userId, $roleIds);

        $ownerJoin = $this->hasUsersTable()
            ? 'LEFT JOIN users u_owner ON u_owner.id = c.user_id'
            : '';
        $ownerNameSelect = $this->hasUsersTable()
            ? 'u_owner.name AS owner_name'
            : 'NULL AS owner_name';
        $ownerEmailSelect = $this->hasUsersTable()
            ? 'u_owner.email AS owner_email'
            : 'NULL AS owner_email';

        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    cat.nome   AS categoria_nome,
                    cat.colore AS categoria_colore,
                    (c.user_id = ?) AS is_owner,
                    {$ownerNameSelect},
                    {$ownerEmailSelect}
             FROM {$this->table} c
             LEFT JOIN contact_categories cat ON cat.id = c.categoria_id
             {$ownerJoin}
             WHERE c.id = ? AND ({$visSql})"
        );
        $stmt->execute(array_merge([$userId, $id], $visParams));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Versione retro-compatibile: include solo i contatti owned (mantenuto per chi non vuole share).
     */
    public function findWithDetails(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, cat.nome AS categoria_nome, cat.colore AS categoria_colore
             FROM {$this->table} c
             LEFT JOIN contact_categories cat ON cat.id = c.categoria_id
             WHERE c.id = ? AND c.user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Owner-only: usato per gating di edit/update/delete/share. */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** True se l'utente è owner del contatto o ha un ruolo destinatario di una share. */
    public function isAccessible(int $id, int $userId, array $roleIds = []): bool
    {
        [$visSql, $visParams] = $this->buildVisibilityClause($userId, $roleIds);
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM {$this->table} c WHERE c.id = ? AND ({$visSql}) LIMIT 1"
        );
        $stmt->execute(array_merge([$id], $visParams));
        return (bool) $stmt->fetchColumn();
    }

    public function togglePreferito(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET preferito = 1 - preferito WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function getPreferito(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT preferito FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getAllTags(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT tags FROM {$this->table}
             WHERE user_id = ? AND tags IS NOT NULL AND tags != ''"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $tags = [];
        foreach ($rows as $raw) {
            foreach (array_map('trim', explode(',', $raw)) as $tag) {
                if ($tag !== '') {
                    $tags[$tag] = true;
                }
            }
        }
        ksort($tags);
        return array_keys($tags);
    }

    public function getStats(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS totale, SUM(preferito) AS preferiti
             FROM {$this->table} WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return [
            'totale'   => (int) ($row['totale']   ?? 0),
            'preferiti' => (int) ($row['preferiti'] ?? 0),
        ];
    }

    // ── Sharing ──────────────────────────────────────────────────────────────

    /**
     * Lista delle condivisioni di un contatto, con dettagli ruolo e chi ha condiviso.
     *
     * @return array<int,array{id:int,role_id:int,role_name:string,role_slug:string,
     *                         shared_by_user_id:int,shared_by_name:?string,created_at:string}>
     */
    public function listShares(int $contattoId): array
    {
        $sharedByJoin = $this->hasUsersTable()
            ? 'LEFT JOIN users u ON u.id = s.shared_by_user_id'
            : '';
        $sharedByNameSelect = $this->hasUsersTable()
            ? 'u.name AS shared_by_name'
            : 'NULL AS shared_by_name';

        $stmt = $this->pdo->prepare(
            "SELECT s.id, s.role_id, s.shared_by_user_id, s.created_at,
                    r.name AS role_name, r.slug AS role_slug,
                    {$sharedByNameSelect}
             FROM contact_shares s
             INNER JOIN roles r ON r.id = s.role_id
             {$sharedByJoin}
             WHERE s.contatto_id = ?
             ORDER BY r.name ASC"
        );
        $stmt->execute([$contattoId]);
        return $stmt->fetchAll();
    }

    /** Restituisce solo i role_id con cui il contatto è condiviso. */
    public function listShareRoleIds(int $contattoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT role_id FROM contact_shares WHERE contatto_id = ?'
        );
        $stmt->execute([$contattoId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Applica un delta di condivisioni (add + remove) in un'unica transazione:
     * un fallimento a metà non lascia lo stato di sharing incoerente.
     */
    public function replaceShares(int $contattoId, array $toAdd, array $toRemove, int $sharedByUserId): void
    {
        if (empty($toAdd) && empty($toRemove)) {
            return;
        }

        $this->transaction(function () use ($contattoId, $toAdd, $toRemove, $sharedByUserId): void {
            foreach ($toAdd as $roleId) {
                $this->addShare($contattoId, (int) $roleId, $sharedByUserId);
            }
            foreach ($toRemove as $roleId) {
                $this->removeShare($contattoId, (int) $roleId);
            }
        });
    }

    /** Idempotente via UNIQUE (contatto_id, role_id). */
    public function addShare(int $contattoId, int $roleId, int $sharedByUserId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO contact_shares (contatto_id, role_id, shared_by_user_id)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$contattoId, $roleId, $sharedByUserId]);
    }

    public function removeShare(int $contattoId, int $roleId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM contact_shares WHERE contatto_id = ? AND role_id = ?'
        );
        $stmt->execute([$contattoId, $roleId]);
        return $stmt->rowCount() > 0;
    }

    public function clearShares(int $contattoId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM contact_shares WHERE contatto_id = ?'
        );
        $stmt->execute([$contattoId]);
        return $stmt->rowCount();
    }

    // ── Helpers privati ─────────────────────────────────────────────────────

    /**
     * Costruisce la WHERE clause di visibilità per una riga `c` (alias di `contatti`).
     * Restituisce ['SQL', [params]].
     *
     * Logica:
     *   - sempre: c.user_id = ?  (proprietario)
     *   - se $roleIds non vuoto: OR EXISTS (SELECT 1 FROM contact_shares s
     *                                       WHERE s.contatto_id = c.id AND s.role_id IN (...))
     */
    private function buildVisibilityClause(int $userId, array $roleIds): array
    {
        $clauses = ['c.user_id = ?'];
        $params  = [$userId];

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $clauses[]    = "EXISTS (SELECT 1 FROM contact_shares s
                                     WHERE s.contatto_id = c.id
                                       AND s.role_id IN ({$placeholders}))";
            $params       = array_merge($params, $roleIds);
        }

        return [implode(' OR ', $clauses), $params];
    }

    private function hasUsersTable(): bool
    {
        if ($this->usersTableAvailable !== null) {
            return $this->usersTableAvailable;
        }

        return $this->usersTableAvailable = $this->tableExists('users');
    }

    private function tableExists(string $table): bool
    {
        if ($this->isSqlite()) {
            $stmt = $this->pdo->prepare('PRAGMA table_info("' . str_replace('"', '""', $table) . '")');
            $stmt->execute();
            return $stmt->fetch() !== false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
