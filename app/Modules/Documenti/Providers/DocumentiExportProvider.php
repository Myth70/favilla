<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Providers;

use App\Contracts\ExportableModule;
use App\Modules\Documenti\Services\DocumentiRecipientService;

class DocumentiExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'documenti',
                'label'      => 'Documenti',
                'icon'       => 'fa-file-alt',
                'permission' => 'documenti.export',
                'fields'     => [
                    ['name' => 'id',            'label' => 'ID',          'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'protocollo', 'label' => 'Protocollo', 'type' => 'string', 'sortable' => true,  'filterable' => true],
                    ['name' => 'titolo',         'label' => 'Titolo',      'type' => 'string',  'sortable' => true,  'filterable' => true],
                    ['name' => 'stato',          'label' => 'Stato',       'type' => 'enum',    'sortable' => true,  'filterable' => true,
                        'enum_values' => ['bozza','inviato','in_controllo','controllato','in_approvazione','approvato','pubblicato','scaduto','rifiutato']],
                    ['name' => 'categoria_nome', 'label' => 'Categoria',   'type' => 'string',  'sortable' => true,  'filterable' => true],
                    ['name' => 'owner_name',     'label' => 'Proprietario','type' => 'string',  'sortable' => true,  'filterable' => true],
                    ['name' => 'scade_il',       'label' => 'Scadenza',    'type' => 'date',    'sortable' => true,  'filterable' => true],
                    ['name' => 'created_at',     'label' => 'Creato il',   'type' => 'datetime','sortable' => true,  'filterable' => false],
                    ['name' => 'updated_at',     'label' => 'Aggiornato il','type' => 'datetime','sortable' => true, 'filterable' => false],
                ],
            ],
        ];
    }

    public function getExportData(
        string $sourceKey,
        array  $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        int    $limit = 10000
    ): array {
        if ($sourceKey !== 'documenti') {
            return [];
        }

        if (!has_permission('documenti.export')) {
            return [];
        }

        try {
            $pdo = app(\PDO::class);

            $allowedSort = ['id','protocollo','titolo','stato','categoria_nome','owner_name','scade_il','created_at','updated_at'];
            if (!in_array($sortBy, $allowedSort, true)) {
                $sortBy = 'created_at';
            }
            $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

            $where  = ['d.deleted_at IS NULL'];
            $params = [];

            if (!empty($filters['stato'])) {
                $where[]  = 'd.stato = ?';
                $params[] = $filters['stato'];
            }
            if (!empty($filters['categoria_nome'])) {
                $where[]  = 'c.nome LIKE ?';
                $params[] = '%' . $filters['categoria_nome'] . '%';
            }
            if (!empty($filters['titolo'])) {
                $where[]  = 'd.titolo LIKE ?';
                $params[] = '%' . $filters['titolo'] . '%';
            }

            $whereSql = implode(' AND ', $where);

            // owner_name è risolto separatamente in arricchisciOwner(): si ordina
            // lato SQL sulle colonne dirette e, se richiesto l'ordine per owner_name,
            // lo si applica in PHP dopo l'arricchimento.
            $sqlSort = $sortBy === 'owner_name' ? 'd.created_at' : $sortBy;

            $sql = "SELECT d.id, d.protocollo, d.titolo, d.stato,
                           c.nome AS categoria_nome,
                           d.owner_user_id,
                           d.scade_il, d.created_at, d.updated_at
                    FROM documenti d
                    LEFT JOIN documenti_categorie c ON c.id = d.categoria_id
                    WHERE {$whereSql}
                    ORDER BY {$sqlSort} {$sortDir}
                    LIMIT ?";

            $params[] = $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $this->arricchisciOwner($stmt->fetchAll(\PDO::FETCH_ASSOC));

            if ($sortBy === 'owner_name') {
                usort($rows, static function (array $a, array $b) use ($sortDir): int {
                    $cmp = strcasecmp((string) ($a['owner_name'] ?? ''), (string) ($b['owner_name'] ?? ''));
                    return $sortDir === 'ASC' ? $cmp : -$cmp;
                });
            }

            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    public function getExportModuleName(): string
    {
        return 'Documenti';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-file-alt';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'documenti') {
            return null;
        }

        try {
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare(
                'SELECT d.*, c.nome AS categoria_nome
                 FROM documenti d
                 LEFT JOIN documenti_categorie c ON c.id = d.categoria_id
                 WHERE d.id = ?'
            );
            $stmt->execute([$recordId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            [$row] = $this->arricchisciOwner([$row]);
            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Aggiunge `owner_name` a ogni riga risolvendo `owner_user_id`.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function arricchisciOwner(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }
        $names = app(DocumentiRecipientService::class)->displayNamesByIds(
            array_map(static fn ($r): int => (int) ($r['owner_user_id'] ?? 0), $rows)
        );
        foreach ($rows as &$r) {
            $r['owner_name'] = $names[(int) ($r['owner_user_id'] ?? 0)] ?? null;
        }
        unset($r);
        return $rows;
    }
}
