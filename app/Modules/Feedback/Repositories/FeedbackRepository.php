<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Repositories;

use App\Repositories\BaseRepository;

class FeedbackRepository extends BaseRepository
{
    protected string $table = 'feedback';
    protected bool $timestamps  = true;
    protected bool $softDelete  = true;

    // Audit gestito ESPLICITAMENTE nel Service con voci "snelle" (id/stato/ref).
    // Disattivato qui apposta: BaseRepository salverebbe lo snapshot completo
    // della riga in audit_logs, duplicando dom_snapshot e contesto_json (PII + peso).
    protected bool $auditable   = false;

    protected array $fillable = [
        'ref_code', 'tipo', 'severita', 'stato', 'titolo', 'descrizione', 'passi',
        'pagina_url', 'route_name', 'modulo', 'contesto_json', 'errori_console_json',
        'dom_snapshot', 'user_agent', 'viewport', 'app_version', 'created_by',
        'assegnata_a', 'note_admin',
    ];

    /** Whitelist colonne ordinabili — mai fidarsi dell'input utente. */
    private const SORTS = ['created_at', 'stato', 'severita', 'tipo', 'modulo'];

    /** Colonne "leggere" per la lista: niente blob (contesto_json/dom_snapshot/errori). */
    private const LIST_COLUMNS = 's.id, s.ref_code, s.tipo, s.severita, s.stato, s.titolo, '
        . 's.modulo, s.created_by, s.assegnata_a, s.created_at';

    /**
     * Elenco paginato con filtri e ordinamento sicuro.
     *
     * @return array{data:array,total:int,page:int,perPage:int,lastPage:int,sort:string,dir:string}
     */
    public function listPaginated(array $f, int $page = 1, int $perPage = 20): array
    {
        $sort = in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : 'created_at';
        $dir  = strtoupper((string) ($f['dir'] ?? '')) === 'ASC' ? 'ASC' : 'DESC';

        $where  = ['s.deleted_at IS NULL'];
        $params = [];

        if (($f['stato'] ?? '') !== '') {
            $where[] = 's.stato = ?';
            $params[] = $f['stato'];
        }
        if (($f['tipo'] ?? '') !== '') {
            $where[] = 's.tipo = ?';
            $params[] = $f['tipo'];
        }
        if (($f['severita'] ?? '') !== '') {
            $where[] = 's.severita = ?';
            $params[] = $f['severita'];
        }
        if (($f['modulo'] ?? '') !== '') {
            $where[] = 's.modulo = ?';
            $params[] = $f['modulo'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(s.titolo LIKE ? OR s.descrizione LIKE ? OR s.ref_code LIKE ?)';
            $like = '%' . $f['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} s WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage  = max(1, min(100, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $offset   = ($page - 1) * $perPage;

        // $sort whitelisted; $dir normalizzato; LIMIT/OFFSET interi.
        $sql = 'SELECT ' . self::LIST_COLUMNS . ', u.name AS creatore_nome, a.name AS assegnato_nome
                FROM ' . $this->table . " s
                LEFT JOIN users u ON u.id = s.created_by
                LEFT JOIN users a ON a.id = s.assegnata_a
                WHERE {$whereSql}
                ORDER BY s.{$sort} {$dir}
                {$this->limitClause($perPage, $offset)}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => $lastPage,
            'sort'     => $sort,
            'dir'      => $dir,
        ];
    }

    /** Dettaglio singolo (include i blob) con nome/email autore e assegnatario. */
    public function findDetail(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, u.name AS creatore_nome, u.email AS creatore_email, a.name AS assegnato_nome
             FROM {$this->table} s
             LEFT JOIN users u ON u.id = s.created_by
             LEFT JOIN users a ON a.id = s.assegnata_a
             WHERE s.id = ? AND s.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function refCodeExists(string $ref): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE ref_code = ? LIMIT 1");
        $stmt->execute([$ref]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Trova una segnalazione recente identica dello stesso autore (anti-doppione).
     * Stessa descrizione dallo stesso utente entro la finestra → considerata duplicata.
     */
    public function findRecentDuplicate(?int $userId, string $descrizione, int $withinSeconds): ?array
    {
        $cutoff = date('Y-m-d H:i:s', time() - $withinSeconds);

        if ($userId !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id, ref_code FROM {$this->table}
                 WHERE created_by = ? AND descrizione = ? AND created_at > ?
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$userId, $descrizione, $cutoff]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id, ref_code FROM {$this->table}
                 WHERE created_by IS NULL AND descrizione = ? AND created_at > ?
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$descrizione, $cutoff]);
        }

        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Elimina lo snapshot DOM (data-minimization alla chiusura della segnalazione).
     * UPDATE diretto: NON passa per l'audit, così il DOM non viene copiato altrove.
     */
    public function clearDom(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET dom_snapshot = NULL, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    /** Segnalazioni aperte (nuova + in lavorazione). */
    public function countOpen(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE deleted_at IS NULL AND stato IN ('nuova','in_lavorazione')"
        );
        return (int) $stmt->fetchColumn();
    }

    public function countNew(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE deleted_at IS NULL AND stato = 'nuova'"
        );
        return (int) $stmt->fetchColumn();
    }

    /** Moduli distinti presenti, per popolare il filtro. */
    public function distinctModuli(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT modulo FROM {$this->table}
             WHERE deleted_at IS NULL AND modulo IS NOT NULL AND modulo <> ''
             ORDER BY modulo"
        );
        return array_column($stmt->fetchAll(), 'modulo');
    }

    /** Utenti attivi assegnabili (id => nome). */
    public function assignableUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name FROM users
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY name'
        );
        $out = [];
        foreach ($stmt->fetchAll() as $u) {
            $out[(int) $u['id']] = (string) $u['name'];
        }
        return $out;
    }
}
