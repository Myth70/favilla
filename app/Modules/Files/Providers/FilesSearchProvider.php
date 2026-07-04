<?php

declare(strict_types=1);

namespace App\Modules\Files\Providers;

use App\Contracts\SearchableModule;
use PDO;

class FilesSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        $admin = has_permission('files.admin');

        if ($admin) {
            $stmt = $pdo->prepare(
                'SELECT id, original_name, extension, folder, size_bytes
                 FROM files
                 WHERE deleted_at IS NULL
                   AND (original_name LIKE ? OR description LIKE ? OR tags LIKE ?)
                 ORDER BY created_at DESC
                 LIMIT ?'
            );
            $stmt->execute([$like, $like, $like, $limit]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, original_name, extension, folder, size_bytes
                 FROM files
                 WHERE deleted_at IS NULL
                   AND (created_by = ? OR visibility = 'internal')
                   AND (original_name LIKE ? OR description LIKE ? OR tags LIKE ?)
                 ORDER BY created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $like, $like, $like, $limit]);
        }

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $subtitle = strtoupper($row['extension'] ?? '');
            if ($row['folder']) {
                $subtitle .= ' — ' . $row['folder'];
            }
            $results[] = [
                'title'    => $row['original_name'],
                'subtitle' => $subtitle,
                'url'      => route('files.show', ['id' => $row['id']]),
                'icon'     => 'fa-file',
                'badge'    => null,
            ];
        }
        return $results;
    }

    public function getSearchLabel(): string
    {
        return 'File';
    }

    public function getSearchIcon(): string
    {
        return 'fa-file';
    }
}
