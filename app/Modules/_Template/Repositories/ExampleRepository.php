<?php

declare(strict_types=1);

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  REPOSITORY DI MODULO — Accesso dati con PDO prepared          ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * ISTRUZIONI:
 * 1. Rinomina la classe (es. ClientiRepository)
 * 2. Aggiorna $table con il nome della tabella MySQL
 * 3. Aggiorna il namespace al tuo modulo
 * 4. Aggiorna $fillable con i campi della tua tabella
 * 5. Adatta listPaginated() ai tuoi campi
 *
 * METODI EREDITATI DA BaseRepository:
 *   find(int $id)              → singolo record per ID (o null)
 *   all()                      → tutti i record
 *   where(array $conditions)   → record filtrati ['col' => 'valore']
 *   findBy(string $col, $val)  → primo record dove col = valore
 *   create(array $data)        → INSERT (filterData + timestamps + hooks + audit)
 *   update(int $id, array $d)  → UPDATE (filterData + timestamps + hooks + audit)
 *   delete(int $id)            → DELETE (hooks + audit)
 *   count(array $conditions)   → COUNT con filtri opzionali
 *   transaction(callable $fn)  → BEGIN/COMMIT/ROLLBACK
 *
 * PROPRIETÀ OPT-IN (BaseRepository):
 *   $fillable    → whitelist colonne ammesse in create/update
 *   $guarded     → blacklist colonne escluse (alternativa a fillable)
 *   $timestamps  → auto-imposta created_at/updated_at
 *   $auditable   → auto-log via AuditService
 *   $auditEntity → nome entità per audit (default: $table)
 *   $softDelete  → soft delete con deleted_at
 *
 * HOOK LIFECYCLE (override per logica custom):
 *   beforeCreate(array &$data)           → mutare $data prima dell'INSERT
 *   afterCreate(int $id, array $data)    → post-INSERT (es. notifiche)
 *   beforeUpdate(int $id, array &$data)  → mutare $data prima dell'UPDATE
 *   afterUpdate(int $id, array $data)    → post-UPDATE
 *   beforeDelete(int $id)                → pre-DELETE
 *   afterDelete(int $id)                 → post-DELETE
 */

namespace App\Modules\_Template\Repositories;

use App\Repositories\BaseRepository;

class ExampleRepository extends BaseRepository
{
    protected string $table = 'examples'; // ← CAMBIA con la tua tabella

    // Mass-assignment: SOLO queste colonne passano in create/update
    protected array $fillable = ['name', 'email', 'description', 'status', 'created_by'];

    // Timestamps automatici (created_at + updated_at)
    protected bool $timestamps = true;

    // Soft delete: delete() imposta deleted_at invece di rimuovere la riga.
    // Le query custom qui sotto filtrano sempre deleted_at IS NULL.
    protected bool $softDelete = true;

    // Audit automatico su create/update/delete
    protected bool $auditable = true;

    /**
     * Trova un record visibile all'utente (owner-scoping di default).
     *
     * NOTA VISIBILITÀ: il default è "ogni utente vede solo i propri record"
     * (created_by). Se il modulo deve avere dati condivisi tra tutti gli
     * utenti con il permesso, rimuovi il filtro created_by QUI e nei metodi
     * listPaginated/countByStatus — ma fallo come scelta esplicita,
     * non per omissione. Per visibilità ibride (owner + condivisioni) vedi
     * il pattern di Contacts (buildVisibilityClause).
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? AND created_by = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lista paginata con filtri, ricerca e ordinamento (owner-scoped).
     */
    public function listPaginated(array $filters, int $userId): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 10;
        $offset  = ($page - 1) * $perPage;

        $where  = ['e.created_by = ?', 'e.deleted_at IS NULL'];
        $params = [$userId];

        if (!empty($filters['q'])) {
            $where[]  = '(e.name LIKE ? OR e.email LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $where[]  = 'e.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Ordinamento con whitelist
        $allowedSorts = ['name', 'email', 'status', 'created_at'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true)
            ? $filters['sort']
            : 'created_at';
        $dir = (strtolower($filters['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';

        $countSql = "SELECT COUNT(*) FROM {$this->table} e {$whereSql}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // LIMIT/OFFSET interpolati come interi: MariaDB non li accetta come
        // parametri bindati con i prepared statement nativi (vedi BaseRepository::limitClause()).
        $dataSql = "SELECT e.* FROM {$this->table} e
                    {$whereSql}
                    ORDER BY e.{$sort} {$dir}
                    {$this->limitClause($perPage, $offset)}";
        $stmt = $this->pdo->prepare($dataSql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'data'    => $data,
            'total'   => $total,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Singolo record con JOIN autore (owner-scoped).
     */
    public function findWithAuthor(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, u.name AS author_name
             FROM {$this->table} e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.id = ? AND e.created_by = ? AND e.deleted_at IS NULL"
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Conteggio per status (owner-scoped).
     */
    public function countByStatus(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT status, COUNT(*) as total
             FROM {$this->table}
             WHERE created_by = ? AND deleted_at IS NULL
             GROUP BY status"
        );
        $stmt->execute([$userId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }
}
