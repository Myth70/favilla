<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

/**
 * Letture sull'audit trail dei documenti (tabella `audit_logs`).
 * Incapsula le query SQL per i controller Admin (audit list/detail/export).
 */
class DocumentiAuditService
{
    public const PER_PAGE = 30;

    /**
     * Pagina filtrata di log audit dei documenti.
     *
     * @return array{logs:array,total:int,pages:int,perPage:int}
     */
    public function lista(array $filters, int $page): array
    {
        $pdo    = app(\PDO::class);
        $page   = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        [$where, $params] = $this->buildFilter($filters);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT al.id, al.action, al.entity, al.entity_id, al.user_id, al.ip, al.created_at,
                       u.name AS user_name
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE {$where}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt      = $pdo->prepare($sql);
        $allParams = array_merge($params, [self::PER_PAGE, $offset]);
        foreach ($allParams as $i => $v) {
            $stmt->bindValue($i + 1, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();

        return [
            'logs'    => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total'   => $total,
            'pages'   => (int) ceil($total / self::PER_PAGE),
            'perPage' => self::PER_PAGE,
        ];
    }

    /**
     * Tutti i log per una specifica entità.
     */
    public function dettaglio(string $entity, int $id): array
    {
        $pdo  = app(\PDO::class);
        $stmt = $pdo->prepare(
            'SELECT al.*, u.name AS user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity = ? AND al.entity_id = ?
             ORDER BY al.created_at DESC'
        );
        $stmt->execute([$entity, $id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Righe per l'export CSV (cap 5000), già filtrate.
     */
    public function righeExport(array $filters): array
    {
        $pdo = app(\PDO::class);
        [$where, $params] = $this->buildFilter($filters);
        $stmt = $pdo->prepare(
            "SELECT al.id, al.action, al.entity, al.entity_id, al.ip, al.created_at,
                    u.name AS user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE {$where}
             ORDER BY al.created_at DESC
             LIMIT 5000"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Entità distinte (`documento%`) per i dropdown filtro.
     *
     * @return array<int,string>
     */
    public function entitaDistinte(): array
    {
        return app(\PDO::class)->query(
            "SELECT DISTINCT entity FROM audit_logs WHERE entity LIKE 'documento%' ORDER BY entity"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Azioni distinte (`documento%`) per i dropdown filtro.
     *
     * @return array<int,string>
     */
    public function azioniDistinte(): array
    {
        return app(\PDO::class)->query(
            "SELECT DISTINCT action FROM audit_logs WHERE entity LIKE 'documento%' ORDER BY action"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Costruisce la clausola WHERE filtrata sui log dei documenti.
     *
     * @return array{0:string,1:array<int,string|int>}
     */
    private function buildFilter(array $clean): array
    {
        $where  = "entity LIKE 'documento%'";
        $params = [];

        if (!empty($clean['entity'])) {
            $where    .= ' AND entity = ?';
            $params[]  = $clean['entity'];
        }
        if (!empty($clean['action'])) {
            $where    .= ' AND action = ?';
            $params[]  = $clean['action'];
        }
        if (!empty($clean['q'])) {
            $where    .= ' AND (action LIKE ? OR CAST(entity_id AS CHAR) = ?)';
            $params[]  = '%' . $clean['q'] . '%';
            $params[]  = $clean['q'];
        }
        if (!empty($clean['date_from'])) {
            $where    .= ' AND created_at >= ?';
            $params[]  = $clean['date_from'] . ' 00:00:00';
        }
        if (!empty($clean['date_to'])) {
            $where    .= ' AND created_at <= ?';
            $params[]  = $clean['date_to'] . ' 23:59:59';
        }
        return [$where, $params];
    }
}
