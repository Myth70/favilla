<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Providers;

use App\Contracts\ExportableModule;
use PDO;

class ContactsExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'contacts',
                'label'      => 'Contatti',
                'icon'       => 'fa-address-book',
                'permission' => 'contacts.view',
                'fields'     => [
                    ['name' => 'id',           'label' => 'ID',           'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'nome',         'label' => 'Nome',         'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'cognome',      'label' => 'Cognome',      'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'azienda',      'label' => 'Azienda',      'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'ruolo',        'label' => 'Ruolo',        'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'email',        'label' => 'Email',        'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'telefono',     'label' => 'Telefono',     'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'telefono_alt', 'label' => 'Tel. alt.',    'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'indirizzo',    'label' => 'Indirizzo',    'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'sito_web',     'label' => 'Sito web',     'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'categoria_nome','label' => 'Categoria',   'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'tags',         'label' => 'Tag',          'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'preferito',    'label' => 'Preferito',    'type' => 'boolean',  'sortable' => true,  'filterable' => true],
                    ['name' => 'created_at',   'label' => 'Creato il',    'type' => 'datetime', 'sortable' => true,  'filterable' => true],
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
        if ($sourceKey !== 'contacts') {
            return [];
        }

        $allowedSorts = ['id', 'nome', 'cognome', 'azienda', 'email', 'preferito', 'created_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'nome';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $currentUser = auth();
        $userId      = (int) $currentUser['id'];

        $where  = ['c.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['nome'])) {
            $where[]  = '(c.nome LIKE ? OR c.cognome LIKE ?)';
            $like     = '%' . $filters['nome'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['azienda'])) {
            $where[]  = 'c.azienda LIKE ?';
            $params[] = '%' . $filters['azienda'] . '%';
        }

        if (!empty($filters['email'])) {
            $where[]  = 'c.email LIKE ?';
            $params[] = '%' . $filters['email'] . '%';
        }

        if (!empty($filters['categoria_nome'])) {
            $where[]  = 'cat.nome LIKE ?';
            $params[] = '%' . $filters['categoria_nome'] . '%';
        }

        if (isset($filters['preferito']) && $filters['preferito'] !== '') {
            $where[]  = 'c.preferito = ?';
            $params[] = (int) $filters['preferito'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $pdo      = app(PDO::class);

        $stmt = $pdo->prepare(
            "SELECT c.id, c.nome, c.cognome, c.azienda, c.ruolo, c.email, c.telefono,
                    c.telefono_alt, c.indirizzo, c.sito_web, c.tags, c.preferito, c.created_at,
                    cat.nome AS categoria_nome
             FROM contacts c
             LEFT JOIN contact_categories cat ON cat.id = c.categoria_id
             {$whereSql}
             ORDER BY c.{$sort} {$dir}
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExportModuleName(): string
    {
        return 'Contatti';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-address-book';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'contacts') {
            return null;
        }

        $currentUser = auth();
        $userId      = (int) $currentUser['id'];
        $pdo         = app(PDO::class);

        $stmt = $pdo->prepare(
            'SELECT c.*, cat.nome AS categoria_nome
             FROM contacts c
             LEFT JOIN contact_categories cat ON cat.id = c.categoria_id
             WHERE c.id = ? AND c.user_id = ?'
        );
        $stmt->execute([$recordId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
