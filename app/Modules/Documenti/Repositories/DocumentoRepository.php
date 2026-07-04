<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoRepository extends BaseRepository
{
    protected string $table    = 'documenti';
    protected bool   $timestamps = true;
    protected bool   $softDelete = true;
    protected bool   $auditable  = true;
    protected string $auditEntity = 'documento';

    protected array $fillable = [
        'protocollo', 'titolo', 'descrizione', 'categoria_id', 'owner_user_id',
        'versione_corrente_id', 'file_corrente_id', 'versione_no', 'stato',
        'approvazione_richiesta', 'step_corrente', 'pubblicato_il', 'scade_il',
        'reminder_giorni', 'reminder_stage_inviato', 'reminder_ultimo_invio_at',
        'reminder_destinatari_extra', 'lock_user_id', 'lock_acquired_at', 'tag',
        'created_by', 'updated_by',
    ];

    /**
     * Lista paginata con filtri avanzati.
     */
    public function listPaginated(array $filters = [], bool $adminMode = false): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $where  = ['d.deleted_at IS NULL'];
        $params = [];

        if (!$adminMode) {
            if (!empty($filters['owner_user_id'])) {
                $where[]  = 'd.owner_user_id = ?';
                $params[] = (int) $filters['owner_user_id'];
            }
            // Visibility: stati pubblici visibili a tutti, bozza/rifiutato solo all'owner.
            // Parentesi obbligatorie per non rompere il precedence AND/OR con i filtri successivi.
            $where[]  = '(d.stato NOT IN (\'bozza\',\'rifiutato\') OR d.owner_user_id = ?)';
            $params[] = (int) ($filters['current_user_id'] ?? 0);
        }

        if (!empty($filters['categoria_id'])) {
            $where[]  = 'd.categoria_id = ?';
            $params[] = (int) $filters['categoria_id'];
        }

        if (!empty($filters['stato'])) {
            $stati = (array) $filters['stato'];
            $ph    = implode(',', array_fill(0, count($stati), '?'));
            $where[] = "d.stato IN ({$ph})";
            $params  = array_merge($params, $stati);
        }

        if (!empty($filters['q'])) {
            $where[]  = '(d.titolo LIKE ? OR d.protocollo LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['scadenza'])) {
            if ($filters['scadenza'] === 'prossimi_30') {
                $where[]  = 'd.scade_il BETWEEN ? AND ?';
                $params[] = date('Y-m-d H:i:s');
                $params[] = date('Y-m-d H:i:s', strtotime('+30 days'));
            } elseif ($filters['scadenza'] === 'scaduti') {
                $where[]  = 'd.scade_il < ?';
                $params[] = date('Y-m-d H:i:s');
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $allowedSorts = ['titolo', 'stato', 'created_at', 'scade_il', 'versione_no'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'created_at';
        $dir  = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $countSql = "SELECT COUNT(*) FROM documenti d {$whereSql}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = "SELECT d.*, c.nome AS categoria_nome, c.codice AS categoria_codice
                FROM documenti d
                LEFT JOIN documenti_categorie c ON c.id = d.categoria_id
                {$whereSql}
                ORDER BY d.{$sort} {$dir}
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'data'  => $stmt->fetchAll(),
            'total' => $total,
            'lastPage' => (int) ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /**
     * Documenti con scadenza imminente o scaduti per reminder.
     */
    public function duePendingReminders(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.*, c.reminder_giorni_default
             FROM documenti d
             LEFT JOIN documenti_categorie c ON c.id = d.categoria_id
             WHERE d.stato = 'pubblicato'
               AND d.scade_il IS NOT NULL
               AND d.deleted_at IS NULL"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Documenti pubblicati scaduti.
     */
    public function findPublishedExpired(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, owner_user_id, titolo FROM documenti
             WHERE stato = \'pubblicato\' AND scade_il < ? AND deleted_at IS NULL'
        );
        $stmt->execute([date('Y-m-d H:i:s')]);
        return $stmt->fetchAll();
    }

    /**
     * KPI per la dashboard admin.
     */
    public function kpiByStato(): array
    {
        $stmt = $this->pdo->query(
            'SELECT stato, COUNT(*) AS totale FROM documenti WHERE deleted_at IS NULL GROUP BY stato'
        );
        return $stmt->fetchAll();
    }

    /**
     * Trova con lock pessimistico (deve essere dentro una transaction).
     * `FOR UPDATE` non è supportato da SQLite: omesso sotto test, dove le
     * scritture sono già serializzate a livello di connessione.
     */
    public function findForUpdate(int $id): ?array
    {
        $forUpdate = $this->isSqlite() ? '' : ' FOR UPDATE';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM documenti WHERE id = ? AND deleted_at IS NULL LIMIT 1{$forUpdate}"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
