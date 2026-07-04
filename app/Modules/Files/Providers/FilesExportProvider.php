<?php

declare(strict_types=1);

namespace App\Modules\Files\Providers;

use App\Contracts\ExportableModule;
use App\Modules\Files\Repositories\FilesRepository;
use PDO;

class FilesExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'files',
                'label'      => 'File',
                'icon'       => 'fa-file',
                'permission' => 'files.view',
                'fields'     => [
                    ['name' => 'id',            'label' => 'ID',            'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'original_name', 'label' => 'Nome file',     'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'extension',     'label' => 'Estensione',    'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'mime_type',      'label' => 'Tipo MIME',     'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'size_bytes',     'label' => 'Dimensione',    'type' => 'integer',  'sortable' => true,  'filterable' => false, 'format' => 'bytes'],
                    ['name' => 'folder',         'label' => 'Cartella',      'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'visibility',     'label' => 'Visibilit&agrave;', 'type' => 'enum', 'sortable' => true,  'filterable' => true, 'enum_values' => ['private', 'internal']],
                    ['name' => 'uploader_name',  'label' => 'Caricato da',   'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'created_at',     'label' => 'Data caricamento','type' => 'datetime','sortable' => true,  'filterable' => true],
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
        if ($sourceKey !== 'files') {
            return [];
        }

        $allowedSorts = ['id', 'original_name', 'size_bytes', 'mime_type', 'extension', 'folder', 'visibility', 'created_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $filters['sort'] = $sort;
        $filters['dir']  = $dir;

        $repo = app(FilesRepository::class);

        // Admin view = true to export all files; userId=0 is irrelevant for admin view
        $result = $repo->listPaginated($filters, 0, true, 1, $limit);
        return $result['items'] ?? [];
    }

    public function getExportModuleName(): string
    {
        return 'Files';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-folder-open';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'files') {
            return null;
        }

        $pdo = app(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT f.*, u.name AS uploader_name
             FROM files f
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.id = ? AND f.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
